# Team Membership Ledger Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A WooCommerce back-office plugin that tracks who owes / who has paid for per-season dues and event charges (mostly collected offline), with a per-product roster view and a one-click "charge the whole active roster" season rollover.

**Architecture:** A small plugin with a strict split between **pure domain logic** (roster/ledger math, no WordPress) and **thin WooCommerce adapters** (all `wc_*` calls). The domain layer gets fast unit tests with plain PHPUnit; the adapters and admin screens are verified in the running Studio site. Members are WooCommerce customers with an `active` meta flag; each due/event is a product; orders are the ledger; a custom `requested` order status means "owes."

**Tech Stack:** PHP 7.4+ / WordPress 7.0, WooCommerce (HPOS-enabled), Composer + PHPUnit 9 for domain unit tests. No build step, no JS framework.

## Global Constraints

- WooCommerce is a **hard runtime dependency**. Plugin must not fatal when it is absent — show an admin notice and no-op.
- Declare HPOS compatibility: `custom_order_tables` via `FeaturesUtil::declare_compatibility`. All order reads/writes go through `wc_get_orders` / `wc_create_order` / `WC_Order` — never direct `WP_Query` on `shop_order` posts.
- PHP namespace root: `OrillaEagles\Ledger\`. Text domain: `team-membership-ledger`. Plugin version constant: `TML_VERSION` = `0.1.0`.
- Custom order status slug: `requested` (registered as `wc-requested`), label "Requested". Meaning: owes / unpaid.
- Installment tracking meta key on the order: `_tml_amount_paid` (float). Absent = 0 paid.
- Active-member meta key on the customer/user: `_tml_active` (`'1'` active, `'0'`/absent inactive).
- Capability gate for every admin screen and action: `manage_woocommerce`. Every state-changing POST uses a nonce.
- Data volume is small (dozens–low-hundreds of members/orders); un-indexed line-item iteration in PHP is acceptable and preferred over premature meta denormalization.

---

## File structure

```
team-membership-ledger/
  team-membership-ledger.php        Bootstrap: header, constants, HPOS declare, dependency guard, autoload, boot Plugin
  composer.json                     PSR-4 autoload (src/ + tests/), phpunit dev dep
  phpunit.xml.dist                  Runs tests/ ; no WP bootstrap (pure domain only)
  src/
    Plugin.php                      Wires hooks: order status, admin menu
    Status/OrderStatus.php          Registers the 'requested' order status
    Domain/MemberStatus.php         Status constants: PAID, OWES, NOT_ENTERED
    Domain/LedgerRow.php            Immutable row: member + status + qty + total + paid + balance + date
    Domain/LedgerCalculator.php     PURE: (members[], productRecords[]) -> LedgerRow[]
    Domain/RolloverPlan.php         PURE: (activeMemberIds[], existingCustomerIds[]) -> {toCreate[], toSkip[]}
    Data/RosterRepository.php       WP adapter: list/create member customers, read/write _tml_active
    Data/OrderRepository.php        WC adapter: product line-item records; create rollover orders
    Admin/Menu.php                  Registers the top-level menu + 3 subpages
    Admin/RosterScreen.php          Render roster + active toggle handler
    Admin/LedgerScreen.php          Product dropdown + per-product roster table
    Admin/RolloverScreen.php        Product picker + Generate handler
  tests/
    Domain/LedgerCalculatorTest.php
    Domain/RolloverPlanTest.php
  docs/
    2026-07-23-membership-ledger-design.md
    2026-07-23-membership-ledger-plan.md
  README.md                         Setup: WooCommerce install, HPOS, offline gateways, export plugin
```

---

### Task 1: Plugin bootstrap + WooCommerce dependency guard + HPOS declaration

**Files:**
- Create: `team-membership-ledger.php`
- Create: `src/Plugin.php`

**Interfaces:**
- Produces: `OrillaEagles\Ledger\Plugin::instance(): Plugin` and `Plugin::boot(): void` (idempotent hook registration). Constant `TML_VERSION`, `TML_DIR`, `TML_FILE`.

- [ ] **Step 1: Write the bootstrap file**

`team-membership-ledger.php`:

```php
<?php
/**
 * Plugin Name:       Team Membership Ledger
 * Description:        Track who owes / who has paid for dues and events (offline-friendly) for the Orillia Eagles.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       team-membership-ledger
 *
 * @package OrillaEagles\Ledger
 */

defined( 'ABSPATH' ) || exit;

define( 'TML_VERSION', '0.1.0' );
define( 'TML_FILE', __FILE__ );
define( 'TML_DIR', plugin_dir_path( __FILE__ ) );

// Composer autoloader if present, else a minimal PSR-4 fallback for src/.
if ( file_exists( TML_DIR . 'vendor/autoload.php' ) ) {
	require TML_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		function ( $class ) {
			$prefix = 'OrillaEagles\\Ledger\\';
			if ( strpos( $class, $prefix ) !== 0 ) {
				return;
			}
			$path = TML_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( file_exists( $path ) ) {
				require $path;
			}
		}
	);
}

// Declare HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', TML_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Team Membership Ledger requires WooCommerce to be installed and active.', 'team-membership-ledger' );
					echo '</p></div>';
				}
			);
			return;
		}
		\OrillaEagles\Ledger\Plugin::instance()->boot();
	}
);
```

- [ ] **Step 2: Write the Plugin loader**

`src/Plugin.php`:

```php
<?php
namespace OrillaEagles\Ledger;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;
	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		// Hook registration for later tasks is added here (order status, admin menu).
	}
}
```

- [ ] **Step 3: Verify activation manually**

Run: activate the plugin in wp-admin → Plugins, first with WooCommerce **inactive**, then **active**.
Expected: With WooCommerce off, an error notice appears and no fatal. With WooCommerce on, no notice, no fatal, `WooCommerce → Settings → Advanced → Features` still shows the store as HPOS-compatible.

- [ ] **Step 4: Commit**

```bash
git add team-membership-ledger.php src/Plugin.php
git commit -m "feat: plugin bootstrap with WooCommerce guard and HPOS declaration"
```

---

### Task 2: Composer + PHPUnit harness for pure domain tests

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`

**Interfaces:**
- Produces: `composer test` runs PHPUnit against `tests/`. Autoloads `OrillaEagles\Ledger\` from `src/`.

- [ ] **Step 1: Write composer.json**

```json
{
    "name": "orilla-eagles/team-membership-ledger",
    "description": "Track dues and event payments for the Orillia Eagles.",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6"
    },
    "autoload": {
        "psr-4": { "OrillaEagles\\Ledger\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "OrillaEagles\\Ledger\\Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "allow-plugins": { "*": false }
    }
}
```

- [ ] **Step 2: Write phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="domain">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Install and verify the harness runs**

Run: `cd /Users/ryanwelcher/Studio/orilla-eagles/wp-content/plugins/team-membership-ledger && composer install`
Then: `composer test`
Expected: PHPUnit runs and reports "No tests executed" (0 tests) — the harness works.

- [ ] **Step 4: Commit**

```bash
git add composer.json phpunit.xml.dist composer.lock
echo "/vendor/" >> .gitignore
git add .gitignore
git commit -m "chore: add composer + phpunit harness for domain tests"
```

---

### Task 3: Domain value objects (MemberStatus, LedgerRow)

**Files:**
- Create: `src/Domain/MemberStatus.php`
- Create: `src/Domain/LedgerRow.php`

**Interfaces:**
- Produces:
  - `MemberStatus::PAID = 'paid'`, `MemberStatus::OWES = 'owes'`, `MemberStatus::NOT_ENTERED = 'not_entered'` (string constants).
  - `LedgerRow` constructed as `new LedgerRow(int $memberId, string $name, string $email, string $status, int $qty, float $total, float $paid, ?string $date)`. Read-only public getters: `memberId(), name(), email(), status(), qty(), total(), paid(), balance(): float, date(): ?string`. `balance()` returns `total - paid`.

- [ ] **Step 1: Write MemberStatus**

`src/Domain/MemberStatus.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

final class MemberStatus {
	public const PAID        = 'paid';
	public const OWES        = 'owes';
	public const NOT_ENTERED = 'not_entered';
}
```

- [ ] **Step 2: Write LedgerRow**

`src/Domain/LedgerRow.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerRow {

	private int $member_id;
	private string $name;
	private string $email;
	private string $status;
	private int $qty;
	private float $total;
	private float $paid;
	private ?string $date;

	public function __construct(
		int $member_id,
		string $name,
		string $email,
		string $status,
		int $qty,
		float $total,
		float $paid,
		?string $date
	) {
		$this->member_id = $member_id;
		$this->name      = $name;
		$this->email     = $email;
		$this->status    = $status;
		$this->qty       = $qty;
		$this->total     = $total;
		$this->paid      = $paid;
		$this->date      = $date;
	}

	public function memberId(): int { return $this->member_id; }
	public function name(): string { return $this->name; }
	public function email(): string { return $this->email; }
	public function status(): string { return $this->status; }
	public function qty(): int { return $this->qty; }
	public function total(): float { return $this->total; }
	public function paid(): float { return $this->paid; }
	public function balance(): float { return round( $this->total - $this->paid, 2 ); }
	public function date(): ?string { return $this->date; }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Domain/MemberStatus.php src/Domain/LedgerRow.php
git commit -m "feat: add MemberStatus and LedgerRow value objects"
```

---

### Task 4: LedgerCalculator (pure, TDD)

**Files:**
- Create: `tests/Domain/LedgerCalculatorTest.php`
- Create: `src/Domain/LedgerCalculator.php`

**Interfaces:**
- Consumes: `LedgerRow`, `MemberStatus`.
- Produces: `LedgerCalculator::forProduct(array $members, array $records): array` returning `LedgerRow[]`, one row per member, in the input member order.
  - `$members`: list of `['id'=>int,'name'=>string,'email'=>string]` (already filtered/ordered by caller).
  - `$records`: list of `['customer_id'=>int,'status'=>string,'qty'=>int,'line_total'=>float,'amount_paid'=>float,'date'=>?string]` for ONE product. A member may have 0..n records (installments / re-entries).
  - Roll-up rules per member: sum `qty`, sum `line_total`→`total`, sum `amount_paid`→`paid`. Status = `NOT_ENTERED` if no records; else `PAID` if every record status is `completed`; else `OWES`. Date = earliest non-null record date (string compare on `Y-m-d H:i:s`).

- [ ] **Step 1: Write the failing tests**

`tests/Domain/LedgerCalculatorTest.php`:

```php
<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\MemberStatus;
use PHPUnit\Framework\TestCase;

final class LedgerCalculatorTest extends TestCase {

	private array $members;

	protected function setUp(): void {
		$this->members = array(
			array( 'id' => 1, 'name' => 'Alice', 'email' => 'a@x.com' ),
			array( 'id' => 2, 'name' => 'Bob', 'email' => 'b@x.com' ),
			array( 'id' => 3, 'name' => 'Cara', 'email' => 'c@x.com' ),
		);
	}

	public function test_member_with_no_record_is_not_entered(): void {
		$rows = LedgerCalculator::forProduct( $this->members, array() );
		$this->assertCount( 3, $rows );
		$this->assertSame( MemberStatus::NOT_ENTERED, $rows[0]->status() );
		$this->assertSame( 0.0, $rows[0]->total() );
		$this->assertNull( $rows[0]->date() );
	}

	public function test_completed_record_is_paid(): void {
		$records = array(
			array( 'customer_id' => 1, 'status' => 'completed', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 100.0, 'date' => '2026-01-05 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::PAID, $rows[0]->status() );
		$this->assertSame( 0.0, $rows[0]->balance() );
		$this->assertSame( '2026-01-05 09:00:00', $rows[0]->date() );
	}

	public function test_requested_record_owes_with_balance(): void {
		$records = array(
			array( 'customer_id' => 2, 'status' => 'requested', 'qty' => 2, 'line_total' => 200.0, 'amount_paid' => 50.0, 'date' => '2026-01-06 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		$this->assertSame( MemberStatus::OWES, $rows[1]->status() );
		$this->assertSame( 150.0, $rows[1]->balance() );
		$this->assertSame( 2, $rows[1]->qty() );
	}

	public function test_multiple_records_roll_up_and_earliest_date_wins(): void {
		$records = array(
			array( 'customer_id' => 3, 'status' => 'completed', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 50.0, 'date' => '2026-02-10 09:00:00' ),
			array( 'customer_id' => 3, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-01 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );
		// Mixed statuses -> OWES; totals summed; earliest date.
		$this->assertSame( MemberStatus::OWES, $rows[2]->status() );
		$this->assertSame( 100.0, $rows[2]->total() );
		$this->assertSame( 50.0, $rows[2]->paid() );
		$this->assertSame( '2026-01-01 09:00:00', $rows[2]->date() );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Error: Class "OrillaEagles\Ledger\Domain\LedgerCalculator" not found`.

- [ ] **Step 3: Write the implementation**

`src/Domain/LedgerCalculator.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

final class LedgerCalculator {

	/**
	 * @param array $members list of ['id','name','email'].
	 * @param array $records list of ['customer_id','status','qty','line_total','amount_paid','date'] for one product.
	 * @return LedgerRow[]
	 */
	public static function forProduct( array $members, array $records ): array {
		$by_member = array();
		foreach ( $records as $r ) {
			$by_member[ (int) $r['customer_id'] ][] = $r;
		}

		$rows = array();
		foreach ( $members as $m ) {
			$id  = (int) $m['id'];
			$own = $by_member[ $id ] ?? array();

			if ( empty( $own ) ) {
				$rows[] = new LedgerRow( $id, (string) $m['name'], (string) $m['email'], MemberStatus::NOT_ENTERED, 0, 0.0, 0.0, null );
				continue;
			}

			$qty        = 0;
			$total      = 0.0;
			$paid       = 0.0;
			$all_paid   = true;
			$date       = null;
			foreach ( $own as $r ) {
				$qty   += (int) $r['qty'];
				$total += (float) $r['line_total'];
				$paid  += (float) $r['amount_paid'];
				if ( 'completed' !== $r['status'] ) {
					$all_paid = false;
				}
				if ( ! empty( $r['date'] ) && ( null === $date || $r['date'] < $date ) ) {
					$date = (string) $r['date'];
				}
			}

			$status = $all_paid ? MemberStatus::PAID : MemberStatus::OWES;
			$rows[] = new LedgerRow( $id, (string) $m['name'], (string) $m['email'], $status, $qty, round( $total, 2 ), round( $paid, 2 ), $date );
		}

		return $rows;
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS — 4 tests, all green.

- [ ] **Step 5: Commit**

```bash
git add tests/Domain/LedgerCalculatorTest.php src/Domain/LedgerCalculator.php
git commit -m "feat: LedgerCalculator rolls order records into per-member ledger rows"
```

---

### Task 5: RolloverPlan (pure, TDD)

**Files:**
- Create: `tests/Domain/RolloverPlanTest.php`
- Create: `src/Domain/RolloverPlan.php`

**Interfaces:**
- Produces: `RolloverPlan::build(array $activeMemberIds, array $existingCustomerIds): array` returning `['to_create' => int[], 'to_skip' => int[]]`. Preserves `$activeMemberIds` order. A member id present in `$existingCustomerIds` goes to `to_skip`; otherwise `to_create`. Deduplicates `$activeMemberIds`.

- [ ] **Step 1: Write the failing tests**

`tests/Domain/RolloverPlanTest.php`:

```php
<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\RolloverPlan;
use PHPUnit\Framework\TestCase;

final class RolloverPlanTest extends TestCase {

	public function test_members_without_existing_order_are_created(): void {
		$plan = RolloverPlan::build( array( 1, 2, 3 ), array() );
		$this->assertSame( array( 1, 2, 3 ), $plan['to_create'] );
		$this->assertSame( array(), $plan['to_skip'] );
	}

	public function test_members_with_existing_order_are_skipped(): void {
		$plan = RolloverPlan::build( array( 1, 2, 3 ), array( 2 ) );
		$this->assertSame( array( 1, 3 ), $plan['to_create'] );
		$this->assertSame( array( 2 ), $plan['to_skip'] );
	}

	public function test_duplicate_active_ids_are_collapsed(): void {
		$plan = RolloverPlan::build( array( 1, 1, 2 ), array() );
		$this->assertSame( array( 1, 2 ), $plan['to_create'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Class "OrillaEagles\Ledger\Domain\RolloverPlan" not found`.

- [ ] **Step 3: Write the implementation**

`src/Domain/RolloverPlan.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

final class RolloverPlan {

	/**
	 * @param int[] $active_member_ids
	 * @param int[] $existing_customer_ids
	 * @return array{to_create:int[],to_skip:int[]}
	 */
	public static function build( array $active_member_ids, array $existing_customer_ids ): array {
		$existing = array_flip( array_map( 'intval', $existing_customer_ids ) );
		$seen     = array();
		$create   = array();
		$skip     = array();

		foreach ( $active_member_ids as $raw ) {
			$id = (int) $raw;
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			if ( isset( $existing[ $id ] ) ) {
				$skip[] = $id;
			} else {
				$create[] = $id;
			}
		}

		return array( 'to_create' => $create, 'to_skip' => $skip );
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS — 3 tests green (7 total across the suite).

- [ ] **Step 5: Commit**

```bash
git add tests/Domain/RolloverPlanTest.php src/Domain/RolloverPlan.php
git commit -m "feat: RolloverPlan decides which active members to charge vs skip"
```

---

### Task 6: Register the "Requested" custom order status

**Files:**
- Create: `src/Status/OrderStatus.php`
- Modify: `src/Plugin.php` (call `OrderStatus::register()` from `boot()`)

**Interfaces:**
- Consumes: nothing from domain.
- Produces: `OrderStatus::register(): void` (attaches its own hooks). Status slug `requested` / `wc-requested`, label "Requested", appears in the WooCommerce order-status dropdown and admin order list.

- [ ] **Step 1: Write OrderStatus**

`src/Status/OrderStatus.php`:

```php
<?php
namespace OrillaEagles\Ledger\Status;

defined( 'ABSPATH' ) || exit;

final class OrderStatus {

	public const SLUG   = 'requested';       // Used with $order->update_status().
	public const WC_KEY = 'wc-requested';    // Registered post status / wc_order_statuses key.

	public static function register(): void {
		add_action( 'init', array( self::class, 'register_post_status' ) );
		add_filter( 'wc_order_statuses', array( self::class, 'add_to_order_statuses' ) );
	}

	public static function register_post_status(): void {
		register_post_status(
			self::WC_KEY,
			array(
				'label'                     => _x( 'Requested', 'Order status', 'team-membership-ledger' ),
				'public'                    => false,
				'internal'                  => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders. */
				'label_count'               => _n_noop( 'Requested <span class="count">(%s)</span>', 'Requested <span class="count">(%s)</span>', 'team-membership-ledger' ),
			)
		);
	}

	/**
	 * Insert "Requested" right after Pending payment in the status dropdown.
	 *
	 * @param array<string,string> $statuses
	 * @return array<string,string>
	 */
	public static function add_to_order_statuses( array $statuses ): array {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-pending' === $key ) {
				$new[ self::WC_KEY ] = _x( 'Requested', 'Order status', 'team-membership-ledger' );
			}
		}
		return $new;
	}
}
```

- [ ] **Step 2: Wire it into Plugin::boot()**

In `src/Plugin.php`, replace the body comment in `boot()` with:

```php
		\OrillaEagles\Ledger\Status\OrderStatus::register();
```

- [ ] **Step 3: Verify in the running site**

Run: open any order in wp-admin (create a throwaway order if needed) → Status dropdown.
Expected: "Requested" appears as a selectable status, listed just after "Pending payment"; selecting and saving persists it.

- [ ] **Step 4: Commit**

```bash
git add src/Status/OrderStatus.php src/Plugin.php
git commit -m "feat: register 'Requested' custom order status (owes)"
```

---

### Task 7: RosterRepository (WooCommerce/WP adapter)

**Files:**
- Create: `src/Data/RosterRepository.php`

**Interfaces:**
- Produces:
  - `RosterRepository::ACTIVE_META = '_tml_active'`.
  - `activeMembers(): array` and `allMembers(): array` → each item `['id'=>int,'name'=>string,'email'=>string,'active'=>bool]`, ordered by display name. "Members" = WP users with role `customer`.
  - `setActive(int $userId, bool $active): void` (writes `_tml_active` = `'1'`/`'0'`).
  - `createMember(string $firstName, string $lastName, string $email): int` → new customer user id, marked active. Throws `\RuntimeException` on failure.

- [ ] **Step 1: Write RosterRepository**

`src/Data/RosterRepository.php`:

```php
<?php
namespace OrillaEagles\Ledger\Data;

defined( 'ABSPATH' ) || exit;

final class RosterRepository {

	public const ACTIVE_META = '_tml_active';

	/** @return array<int,array{id:int,name:string,email:string,active:bool}> */
	public function allMembers(): array {
		$users = get_users(
			array(
				'role'    => 'customer',
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
		return array_map( array( $this, 'shape' ), $users );
	}

	/** @return array<int,array{id:int,name:string,email:string,active:bool}> */
	public function activeMembers(): array {
		return array_values(
			array_filter(
				$this->allMembers(),
				static function ( $m ) {
					return $m['active'];
				}
			)
		);
	}

	public function setActive( int $user_id, bool $active ): void {
		update_user_meta( $user_id, self::ACTIVE_META, $active ? '1' : '0' );
	}

	public function createMember( string $first_name, string $last_name, string $email ): int {
		$user_id = wc_create_new_customer( sanitize_email( $email ), '', '', array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
		) );
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( $user_id->get_error_message() );
		}
		$this->setActive( (int) $user_id, true );
		return (int) $user_id;
	}

	/**
	 * @param \WP_User $user
	 * @return array{id:int,name:string,email:string,active:bool}
	 */
	private function shape( $user ): array {
		$name = trim( $user->first_name . ' ' . $user->last_name );
		return array(
			'id'     => (int) $user->ID,
			'name'   => '' !== $name ? $name : $user->display_name,
			'email'  => (string) $user->user_email,
			'active' => '1' === get_user_meta( $user->ID, self::ACTIVE_META, true ),
		);
	}
}
```

- [ ] **Step 2: Verify in the running site**

Run: WooCommerce → Customers (or Users), ensure at least one `customer` user exists; then via a quick `wp shell` or a temporary admin notice, call `(new RosterRepository())->allMembers()`.
Expected: returns the customer(s) with `active => false` until toggled; `createMember()` produces a new customer visible under Users with role Customer.

- [ ] **Step 3: Commit**

```bash
git add src/Data/RosterRepository.php
git commit -m "feat: RosterRepository lists/creates member customers and active flag"
```

---

### Task 8: OrderRepository (WooCommerce adapter — records + rollover creation)

**Files:**
- Create: `src/Data/OrderRepository.php`

**Interfaces:**
- Consumes: `OrderStatus::SLUG`.
- Produces:
  - `productRecords(int $productId): array` → list of `['customer_id'=>int,'status'=>string,'qty'=>int,'line_total'=>float,'amount_paid'=>float,'date'=>?string]` — one entry per matching order line item across ALL orders that contain `$productId`. `status` is the order status slug without `wc-` prefix (e.g. `requested`, `completed`). `amount_paid` reads `_tml_amount_paid` meta (0 if absent); for `completed` orders `amount_paid` = `line_total`.
  - `existingCustomerIds(int $productId): int[]` — distinct customer ids from `productRecords()`.
  - `createRequestedOrder(int $customerId, int $productId): int` — creates a `requested` order with the product (qty 1), no online payment, and returns the order id.
  - `sellableProducts(): array` → `['id'=>int,'name'=>string]` for all published products (dropdown source), ordered by name.

- [ ] **Step 1: Write OrderRepository**

`src/Data/OrderRepository.php`:

```php
<?php
namespace OrillaEagles\Ledger\Data;

use OrillaEagles\Ledger\Status\OrderStatus;

defined( 'ABSPATH' ) || exit;

final class OrderRepository {

	public const AMOUNT_PAID_META = '_tml_amount_paid';

	/** @return array<int,array{customer_id:int,status:string,qty:int,line_total:float,amount_paid:float,date:?string}> */
	public function productRecords( int $product_id ): array {
		$orders = wc_get_orders(
			array(
				'limit'  => -1,
				'type'   => 'shop_order',
				'status' => array_keys( wc_get_order_statuses() ), // all statuses
			)
		);

		$records = array();
		foreach ( $orders as $order ) {
			$customer_id = (int) $order->get_customer_id();
			if ( ! $customer_id ) {
				continue;
			}
			$status = $order->get_status(); // slug without wc- prefix
			$date   = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null;

			foreach ( $order->get_items() as $item ) {
				if ( (int) $item->get_product_id() !== $product_id ) {
					continue;
				}
				$line_total = (float) $item->get_total();
				$paid       = ( 'completed' === $status )
					? $line_total
					: (float) $order->get_meta( self::AMOUNT_PAID_META );

				$records[] = array(
					'customer_id' => $customer_id,
					'status'      => $status,
					'qty'         => (int) $item->get_quantity(),
					'line_total'  => $line_total,
					'amount_paid' => $paid,
					'date'        => $date,
				);
			}
		}
		return $records;
	}

	/** @return int[] */
	public function existingCustomerIds( int $product_id ): array {
		$ids = array();
		foreach ( $this->productRecords( $product_id ) as $r ) {
			$ids[ $r['customer_id'] ] = true;
		}
		return array_map( 'intval', array_keys( $ids ) );
	}

	public function createRequestedOrder( int $customer_id, int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			throw new \RuntimeException( 'Product not found: ' . $product_id );
		}
		$order = wc_create_order( array( 'customer_id' => $customer_id ) );
		$order->add_product( $product, 1 );
		$order->set_created_via( 'team-membership-ledger' );
		$order->calculate_totals();
		$order->update_status( OrderStatus::SLUG, __( 'Season rollover charge.', 'team-membership-ledger' ) );
		return (int) $order->get_id();
	}

	/** @return array<int,array{id:int,name:string}> */
	public function sellableProducts(): array {
		$products = wc_get_products(
			array(
				'limit'   => -1,
				'status'  => 'publish',
				'orderby' => 'name',
				'order'   => 'ASC',
			)
		);
		return array_map(
			static function ( $p ) {
				return array( 'id' => (int) $p->get_id(), 'name' => $p->get_name() );
			},
			$products
		);
	}
}
```

- [ ] **Step 2: Verify in the running site**

Run: create a test product and one manual order for a customer containing it, set to Requested; then call `(new OrderRepository())->productRecords( <product_id> )`.
Expected: returns one record with `status => 'requested'`, correct `customer_id`, `qty`, `line_total`. `createRequestedOrder( <customer_id>, <product_id> )` creates a new Requested order visible in WooCommerce → Orders.

- [ ] **Step 3: Commit**

```bash
git add src/Data/OrderRepository.php
git commit -m "feat: OrderRepository reads product line-item records and creates rollover orders"
```

---

### Task 9: Admin menu + Roster screen

**Files:**
- Create: `src/Admin/Menu.php`
- Create: `src/Admin/RosterScreen.php`
- Modify: `src/Plugin.php` (register menu on `admin_menu`, handle roster POST on `admin_init`)

**Interfaces:**
- Consumes: `RosterRepository`.
- Produces: `Menu::register(): void` adds top-level "Membership" menu with subpages Roster (default), Ledger, Rollover. `RosterScreen::render(): void`, `RosterScreen::handlePost(): void`.

- [ ] **Step 1: Write Menu**

`src/Admin/Menu.php`:

```php
<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

final class Menu {

	public const CAP  = 'manage_woocommerce';
	public const SLUG = 'tml-roster';

	public static function register(): void {
		add_menu_page(
			__( 'Membership', 'team-membership-ledger' ),
			__( 'Membership', 'team-membership-ledger' ),
			self::CAP,
			self::SLUG,
			array( RosterScreen::class, 'render' ),
			'dashicons-groups',
			56
		);
		add_submenu_page( self::SLUG, __( 'Roster', 'team-membership-ledger' ), __( 'Roster', 'team-membership-ledger' ), self::CAP, self::SLUG, array( RosterScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Ledger', 'team-membership-ledger' ), __( 'Ledger', 'team-membership-ledger' ), self::CAP, 'tml-ledger', array( LedgerScreen::class, 'render' ) );
		add_submenu_page( self::SLUG, __( 'Season Rollover', 'team-membership-ledger' ), __( 'Season Rollover', 'team-membership-ledger' ), self::CAP, 'tml-rollover', array( RolloverScreen::class, 'render' ) );
	}
}
```

- [ ] **Step 2: Write RosterScreen**

`src/Admin/RosterScreen.php`:

```php
<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\RosterRepository;

defined( 'ABSPATH' ) || exit;

final class RosterScreen {

	public static function handlePost(): void {
		if ( empty( $_POST['tml_roster_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_roster' );

		$repo   = new RosterRepository();
		$action = sanitize_key( wp_unslash( $_POST['tml_roster_action'] ) );

		if ( 'toggle' === $action ) {
			$repo->setActive( absint( $_POST['user_id'] ?? 0 ), ! empty( $_POST['active'] ) );
		} elseif ( 'create' === $action ) {
			try {
				$repo->createMember(
					sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
					sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
					sanitize_email( wp_unslash( $_POST['email'] ?? '' ) )
				);
			} catch ( \RuntimeException $e ) {
				set_transient( 'tml_roster_error', $e->getMessage(), 30 );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . Menu::SLUG ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$members = ( new RosterRepository() )->allMembers();
		$err     = get_transient( 'tml_roster_error' );
		delete_transient( 'tml_roster_error' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Roster', 'team-membership-ledger' ); ?></h1>
			<?php if ( $err ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $err ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Add member', 'team-membership-ledger' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'tml_roster' ); ?>
				<input type="hidden" name="tml_roster_action" value="create" />
				<input type="text" name="first_name" placeholder="<?php esc_attr_e( 'First name', 'team-membership-ledger' ); ?>" required />
				<input type="text" name="last_name" placeholder="<?php esc_attr_e( 'Last name', 'team-membership-ledger' ); ?>" required />
				<input type="email" name="email" placeholder="<?php esc_attr_e( 'Email', 'team-membership-ledger' ); ?>" required />
				<?php submit_button( __( 'Add member', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Members', 'team-membership-ledger' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Name', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Email', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Active', 'team-membership-ledger' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $members as $m ) : ?>
					<tr>
						<td><?php echo esc_html( $m['name'] ); ?></td>
						<td><?php echo esc_html( $m['email'] ); ?></td>
						<td>
							<form method="post" style="margin:0">
								<?php wp_nonce_field( 'tml_roster' ); ?>
								<input type="hidden" name="tml_roster_action" value="toggle" />
								<input type="hidden" name="user_id" value="<?php echo esc_attr( $m['id'] ); ?>" />
								<input type="hidden" name="active" value="<?php echo $m['active'] ? '0' : '1'; ?>" />
								<button class="button">
									<?php echo $m['active'] ? esc_html__( 'Active ✓ (click to deactivate)', 'team-membership-ledger' ) : esc_html__( 'Inactive (click to activate)', 'team-membership-ledger' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
```

- [ ] **Step 3: Wire menu + POST handler into Plugin::boot()**

In `src/Plugin.php` `boot()`, after the OrderStatus line add:

```php
		add_action( 'admin_menu', array( \OrillaEagles\Ledger\Admin\Menu::class, 'register' ) );
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\RosterScreen::class, 'handlePost' ) );
```

- [ ] **Step 4: Verify in the running site**

Run: reload wp-admin. Open Membership → Roster.
Expected: the roster table lists customers; "Add member" creates a new active customer; the Active button toggles and persists across reload.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/Menu.php src/Admin/RosterScreen.php src/Plugin.php
git commit -m "feat: admin Membership menu and Roster screen with active toggle"
```

---

### Task 10: Ledger screen (per-product roster view)

**Files:**
- Create: `src/Admin/LedgerScreen.php`

**Interfaces:**
- Consumes: `RosterRepository`, `OrderRepository`, `LedgerCalculator`, `MemberStatus`.
- Produces: `LedgerScreen::render(): void` — product dropdown (GET `product_id`); renders one row per active member with status/qty/total/paid/balance/date. No POST, no nonce needed (read-only GET).

- [ ] **Step 1: Write LedgerScreen**

`src/Admin/LedgerScreen.php`:

```php
<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\MemberStatus;

defined( 'ABSPATH' ) || exit;

final class LedgerScreen {

	private static function label( string $status ): string {
		switch ( $status ) {
			case MemberStatus::PAID:
				return __( 'Paid', 'team-membership-ledger' );
			case MemberStatus::OWES:
				return __( 'Owes', 'team-membership-ledger' );
			default:
				return __( 'Not entered', 'team-membership-ledger' );
		}
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$order_repo  = new OrderRepository();
		$roster_repo = new RosterRepository();

		$products   = $order_repo->sellableProducts();
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$rows = array();
		if ( $product_id ) {
			$members = $roster_repo->activeMembers();
			$records = $order_repo->productRecords( $product_id );
			$rows    = LedgerCalculator::forProduct( $members, $records );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ledger', 'team-membership-ledger' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="tml-ledger" />
				<select name="product_id" onchange="this.form.submit()">
					<option value="0"><?php esc_html_e( '— Select a product —', 'team-membership-ledger' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $product_id, $p['id'] ); ?>>
							<?php echo esc_html( $p['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>

			<?php if ( $product_id ) : ?>
			<table class="widefat striped" style="margin-top:1em">
				<thead><tr>
					<th><?php esc_html_e( 'Member', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Status', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Qty', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Total', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Paid', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Balance', 'team-membership-ledger' ); ?></th>
					<th><?php esc_html_e( 'Date', 'team-membership-ledger' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->name() ); ?></td>
						<td><?php echo esc_html( self::label( $row->status() ) ); ?></td>
						<td><?php echo esc_html( (string) $row->qty() ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->total() ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->paid() ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $row->balance() ) ); ?></td>
						<td><?php echo esc_html( $row->date() ? substr( $row->date(), 0, 10 ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
```

- [ ] **Step 2: Verify in the running site**

Run: Membership → Ledger, pick the test product.
Expected: every active member appears as a row; the member with a Requested order shows "Owes" + balance; members with no order show "Not entered"; a Completed order shows "Paid" / balance 0.

- [ ] **Step 3: Commit**

```bash
git add src/Admin/LedgerScreen.php
git commit -m "feat: per-product Ledger screen showing every active member's status"
```

---

### Task 11: Season Rollover screen (bulk generate)

**Files:**
- Create: `src/Admin/RolloverScreen.php`
- Modify: `src/Plugin.php` (register rollover POST handler on `admin_init`)

**Interfaces:**
- Consumes: `RosterRepository`, `OrderRepository`, `RolloverPlan`.
- Produces: `RolloverScreen::render(): void`, `RolloverScreen::handlePost(): void`. On Generate: builds a `RolloverPlan` from active members vs existing customers for the chosen product, creates a Requested order for each `to_create`, reports counts.

- [ ] **Step 1: Write RolloverScreen**

`src/Admin/RolloverScreen.php`:

```php
<?php
namespace OrillaEagles\Ledger\Admin;

use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\RolloverPlan;

defined( 'ABSPATH' ) || exit;

final class RolloverScreen {

	public static function handlePost(): void {
		if ( empty( $_POST['tml_rollover_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_rollover' );

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			set_transient( 'tml_rollover_notice', __( 'Please choose a product.', 'team-membership-ledger' ), 30 );
			self::redirect();
		}

		$roster = new RosterRepository();
		$orders = new OrderRepository();

		$active_ids = array_map(
			static function ( $m ) {
				return (int) $m['id'];
			},
			$roster->activeMembers()
		);
		$plan = RolloverPlan::build( $active_ids, $orders->existingCustomerIds( $product_id ) );

		$created = 0;
		foreach ( $plan['to_create'] as $customer_id ) {
			try {
				$orders->createRequestedOrder( $customer_id, $product_id );
				$created++;
			} catch ( \RuntimeException $e ) {
				// Skip a single failure; continue the batch.
				continue;
			}
		}

		set_transient(
			'tml_rollover_notice',
			sprintf(
				/* translators: 1: created count, 2: skipped count. */
				__( '%1$d order(s) created, %2$d skipped (already had one).', 'team-membership-ledger' ),
				$created,
				count( $plan['to_skip'] )
			),
			30
		);
		self::redirect();
	}

	private static function redirect(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=tml-rollover' ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		$products = ( new OrderRepository() )->sellableProducts();
		$notice   = get_transient( 'tml_rollover_notice' );
		delete_transient( 'tml_rollover_notice' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Season Rollover', 'team-membership-ledger' ); ?></h1>
			<?php if ( $notice ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Creates one "Requested" order for the chosen product against every active member who does not already have one.', 'team-membership-ledger' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'tml_rollover' ); ?>
				<input type="hidden" name="tml_rollover_action" value="generate" />
				<select name="product_id" required>
					<option value="0"><?php esc_html_e( '— Select a product —', 'team-membership-ledger' ); ?></option>
					<?php foreach ( $products as $p ) : ?>
						<option value="<?php echo esc_attr( $p['id'] ); ?>"><?php echo esc_html( $p['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Generate orders for active roster', 'team-membership-ledger' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
```

- [ ] **Step 2: Register the POST handler in Plugin::boot()**

In `src/Plugin.php` `boot()`, add on `admin_init`:

```php
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\RolloverScreen::class, 'handlePost' ) );
```

- [ ] **Step 3: Verify in the running site**

Run: Membership → Season Rollover, pick a season dues product, click Generate. Then run it a second time.
Expected: first run reports "N created, 0 skipped" and N Requested orders appear in WooCommerce → Orders (one per active member); second run reports "0 created, N skipped." The Ledger screen for that product now shows those members as "Owes."

- [ ] **Step 4: Commit**

```bash
git add src/Admin/RolloverScreen.php src/Plugin.php
git commit -m "feat: Season Rollover generates Requested orders for the active roster"
```

---

### Task 12: README (setup, offline gateways, treasurer export)

**Files:**
- Create: `README.md`

**Interfaces:** none (documentation).

- [ ] **Step 1: Write README**

`README.md`:

```markdown
# Team Membership Ledger

Internal WooCommerce back-office for the Orillia Eagles: track who owes / who
has paid for per-season dues and events (banquets, game tickets), including
money collected offline. No public storefront, no member logins.

## Requirements & setup

1. **WooCommerce** installed and active (this plugin no-ops without it).
2. **HPOS** (WooCommerce → Settings → Advanced → Features → High-performance
   order storage) is supported; leave it on.
3. **Offline payment methods** — WooCommerce → Settings → Payments. Enable and
   rename the built-in offline gateways to your reality:
   - *Cash on delivery* → "Cash"
   - *Direct bank transfer (BACS)* → "e-Transfer"
   - *Cheque* → "Cheque"
4. **Products** — create one product per charge, grouped by category
   (Dues / Banquets / Game Tickets). Use one product per season, e.g.
   "2027 Membership Dues".
5. **Treasurer exports** (optional, free) — install *Advanced Order Export for
   WooCommerce* to export who-paid-what CSVs filtered by product + status.

## Daily use

- **Membership → Roster** — add members (WooCommerce customers) and mark them
  Active/Inactive. Only Active members are charged by Season Rollover.
- **Membership → Ledger** — pick a product to see every active member's status
  (Paid / Owes / Not entered), quantity, total, amount paid, and balance.
- **Membership → Season Rollover** — pick a product and click Generate to raise
  a "Requested" (unpaid) order for every active member who doesn't already have
  one. Safe to re-run.

## Recording payments

- An order's **status is the paid flag**: *Requested* = owes, *Completed* = paid.
- For **installments**, keep the order at Requested/On-hold and set the order
  meta `_tml_amount_paid` to the running amount received; the Ledger shows the
  remaining balance. Mark the order Completed once fully paid.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: setup, offline gateways, and treasurer workflow"
```

---

## Self-review notes

- **Spec coverage:** roster w/ active flag (Tasks 7, 9), per-product ledger incl. not-entered members (Tasks 4, 10), season rollover skip-existing (Tasks 5, 11), offline status-as-ledger + Requested status (Tasks 6, 12), installments via `_tml_amount_paid` (Tasks 8, 10, 12), exports/gateways (Task 12), WooCommerce dependency + HPOS (Task 1). All design sections map to tasks.
- **Types consistent:** `productRecords()` record shape matches `LedgerCalculator::forProduct()` `$records`; `existingCustomerIds()` feeds `RolloverPlan::build()`; `activeMembers()` shape (`id/name/email/active`) matches `LedgerCalculator` `$members` (extra `active` key ignored). Status slug `requested` used identically in `OrderStatus::SLUG`, `productRecords()`, and rollover.
- **Manual-verification tasks (7–12):** these touch live WooCommerce, so they use in-site verification rather than PHPUnit; the pure domain logic they depend on (Tasks 4–5) is unit-tested.
```
