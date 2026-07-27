# Ledger Inline Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Let coordinators record full and incremental partial payments directly from the Ledger screen, creating the charge on the fly for not-entered members.

**Architecture:** A new pure `PaymentCalculator` holds the money math (unit-tested). `OrderRepository` gains `markPaid()`/`addPayment()` write methods and starts stamping each record with its `order_id`; `LedgerCalculator`/`LedgerRow` carry those ids so a row knows which order to act on. `LedgerScreen` becomes read+write with a secured POST handler.

**Tech Stack:** PHP 7.4+ / WordPress, WooCommerce (HPOS), PHPUnit 9. Extends the existing `team-membership-ledger` plugin.

## Global Constraints

- Namespace root `OrillaEagles\Ledger\`. Text domain `team-membership-ledger`.
- Every admin action gated by `manage_woocommerce` + a nonce; inputs sanitized (`absint` ids, float amount `> 0`), output escaped; PRG redirect preserving `product_id`.
- All order access via WooCommerce CRUD (`wc_get_order`, `WC_Order`), HPOS-safe. Installment meta key `_tml_amount_paid`. Paid status = `completed`; owes = `requested`.
- Money math lives in `PaymentCalculator` (pure), not in WordPress code. `new_paid = round(current + increment, 2)`; auto-complete when `new_paid >= round(order_total, 2)`.
- Do not break the existing 9 unit tests.

---

## File structure (changes)

```
src/Domain/PaymentCalculator.php    NEW  pure money math
src/Domain/LedgerRow.php            MOD  + order_ids field & getters
src/Domain/LedgerCalculator.php     MOD  collect distinct order ids into rows
src/Data/OrderRepository.php        MOD  + order_id in records; + markPaid(); + addPayment()
src/Admin/LedgerScreen.php          MOD  per-row actions + handlePost()
src/Plugin.php                      MOD  admin_init -> LedgerScreen::handlePost
tests/Domain/PaymentCalculatorTest.php  NEW
tests/Domain/LedgerCalculatorTest.php   MOD  + order-id collection test
README.md                           MOD  document inline payments
```

---

### Task 1: PaymentCalculator (pure, TDD)

**Files:**
- Create: `tests/Domain/PaymentCalculatorTest.php`
- Create: `src/Domain/PaymentCalculator.php`

**Interfaces:**
- Produces: `PaymentCalculator::apply(float $currentPaid, float $increment, float $orderTotal): array` → `['new_paid' => float, 'should_complete' => bool]`. `new_paid = round(currentPaid + increment, 2)`; `should_complete = new_paid >= round(orderTotal, 2)`.

- [ ] **Step 1: Write the failing tests**

`tests/Domain/PaymentCalculatorTest.php`:

```php
<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\PaymentCalculator;
use PHPUnit\Framework\TestCase;

final class PaymentCalculatorTest extends TestCase {

	public function test_first_partial_payment_does_not_complete(): void {
		$r = PaymentCalculator::apply( 0.0, 50.0, 200.0 );
		$this->assertSame( 50.0, $r['new_paid'] );
		$this->assertFalse( $r['should_complete'] );
	}

	public function test_incremental_payment_accumulates(): void {
		$r = PaymentCalculator::apply( 50.0, 30.0, 200.0 );
		$this->assertSame( 80.0, $r['new_paid'] );
		$this->assertFalse( $r['should_complete'] );
	}

	public function test_reaching_total_completes(): void {
		$r = PaymentCalculator::apply( 150.0, 50.0, 200.0 );
		$this->assertSame( 200.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_overpayment_completes(): void {
		$r = PaymentCalculator::apply( 150.0, 60.0, 200.0 );
		$this->assertSame( 210.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_exact_single_payment_completes(): void {
		$r = PaymentCalculator::apply( 0.0, 100.0, 100.0 );
		$this->assertSame( 100.0, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}

	public function test_float_rounding_is_stable(): void {
		$r = PaymentCalculator::apply( 0.1, 0.2, 0.3 );
		$this->assertSame( 0.3, $r['new_paid'] );
		$this->assertTrue( $r['should_complete'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer test`
Expected: FAIL — `Class "OrillaEagles\Ledger\Domain\PaymentCalculator" not found`.

- [ ] **Step 3: Write the implementation**

`src/Domain/PaymentCalculator.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

final class PaymentCalculator {

	/**
	 * @return array{new_paid:float,should_complete:bool}
	 */
	public static function apply( float $current_paid, float $increment, float $order_total ): array {
		$new_paid        = round( $current_paid + $increment, 2 );
		$should_complete = $new_paid >= round( $order_total, 2 );
		return array(
			'new_paid'        => $new_paid,
			'should_complete' => $should_complete,
		);
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer test`
Expected: PASS — 6 new tests green (15 total).

- [ ] **Step 5: Commit**

```bash
git add tests/Domain/PaymentCalculatorTest.php src/Domain/PaymentCalculator.php
git commit -m "feat: PaymentCalculator computes running total and auto-complete"
```

---

### Task 2: Carry order_id from records into ledger rows

**Files:**
- Modify: `src/Data/OrderRepository.php` (add `order_id` to each record in `productRecords()`)
- Modify: `src/Domain/LedgerRow.php` (add `order_ids` param + getters)
- Modify: `src/Domain/LedgerCalculator.php` (collect distinct order ids per member)
- Modify: `tests/Domain/LedgerCalculatorTest.php` (add order-id collection test)

**Interfaces:**
- Consumes: existing `LedgerRow`, `MemberStatus`.
- Produces:
  - `productRecords()` record now includes `'order_id' => int`.
  - `LedgerRow` constructor gains a trailing `array $order_ids = array()` param; new getters `orderIds(): int[]`, `orderCount(): int`, `singleOrderId(): ?int` (the id when exactly one, else null). Ids are de-duplicated and int-cast.
  - `LedgerCalculator::forProduct` collects each member's distinct non-zero `order_id`s into the row.

- [ ] **Step 1: Add the order-id collection test**

Add this method to `tests/Domain/LedgerCalculatorTest.php` (inside the existing class; the `use` imports for `LedgerCalculator` and `MemberStatus` already exist, and `$this->members` is defined in `setUp()`):

```php
	public function test_row_carries_distinct_order_ids(): void {
		$records = array(
			array( 'customer_id' => 1, 'order_id' => 501, 'status' => 'requested', 'qty' => 1, 'line_total' => 100.0, 'amount_paid' => 0.0, 'date' => '2026-01-05 09:00:00' ),
			array( 'customer_id' => 2, 'order_id' => 502, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-06 09:00:00' ),
			array( 'customer_id' => 2, 'order_id' => 502, 'status' => 'requested', 'qty' => 1, 'line_total' => 50.0, 'amount_paid' => 0.0, 'date' => '2026-01-06 09:00:00' ),
		);
		$rows = LedgerCalculator::forProduct( $this->members, $records );

		// Alice: one order.
		$this->assertSame( array( 501 ), $rows[0]->orderIds() );
		$this->assertSame( 501, $rows[0]->singleOrderId() );
		$this->assertSame( 1, $rows[0]->orderCount() );

		// Bob: two records, same order id -> collapsed to one.
		$this->assertSame( array( 502 ), $rows[1]->orderIds() );
		$this->assertSame( 502, $rows[1]->singleOrderId() );

		// Cara: no records -> no orders.
		$this->assertSame( array(), $rows[2]->orderIds() );
		$this->assertNull( $rows[2]->singleOrderId() );
		$this->assertSame( 0, $rows[2]->orderCount() );
	}
```

- [ ] **Step 2: Run to verify it fails**

Run: `composer test`
Expected: FAIL — `Error: Call to undefined method OrillaEagles\Ledger\Domain\LedgerRow::orderIds()`.

- [ ] **Step 3: Add order_ids to LedgerRow**

In `src/Domain/LedgerRow.php`, add the property, constructor param, and getters. Add a private property alongside the others:

```php
	private array $order_ids;
```

Change the constructor signature and body to accept and store `order_ids` (append the new parameter at the END so existing calls stay valid):

```php
	public function __construct(
		int $member_id,
		string $name,
		string $email,
		string $status,
		int $qty,
		float $total,
		float $paid,
		?string $date,
		array $order_ids = array()
	) {
		$this->member_id = $member_id;
		$this->name      = $name;
		$this->email     = $email;
		$this->status    = $status;
		$this->qty       = $qty;
		$this->total     = $total;
		$this->paid      = $paid;
		$this->date      = $date;
		$this->order_ids = array_values( array_unique( array_map( 'intval', $order_ids ) ) );
	}
```

Add these getters after the existing `date()` getter:

```php
	/** @return int[] */
	public function orderIds(): array { return $this->order_ids; }
	public function orderCount(): int { return count( $this->order_ids ); }
	public function singleOrderId(): ?int { return 1 === count( $this->order_ids ) ? $this->order_ids[0] : null; }
```

- [ ] **Step 4: Collect order ids in LedgerCalculator**

In `src/Domain/LedgerCalculator.php`, inside the `foreach ( $members as $m )` loop, add an `$order_ids` accumulator and gather ids from each record. Replace the per-member aggregation block so it reads:

```php
			$qty      = 0;
			$total    = 0.0;
			$paid     = 0.0;
			$all_paid = true;
			$date     = null;
			$order_ids = array();
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
				if ( ! empty( $r['order_id'] ) ) {
					$order_ids[] = (int) $r['order_id'];
				}
			}

			$status = $all_paid ? MemberStatus::PAID : MemberStatus::OWES;
			$rows[] = new LedgerRow( $id, (string) $m['name'], (string) $m['email'], $status, $qty, round( $total, 2 ), round( $paid, 2 ), $date, $order_ids );
```

(The not-entered branch that builds a `LedgerRow` for members with no records stays as-is — it omits `$order_ids`, so it defaults to an empty array.)

- [ ] **Step 5: Add order_id to OrderRepository records**

In `src/Data/OrderRepository.php`, inside `productRecords()`, add `order_id` to the record array pushed onto `$records` (the array currently has `customer_id`, `status`, `qty`, `line_total`, `amount_paid`, `date`):

```php
				$records[] = array(
					'customer_id' => $customer_id,
					'order_id'    => (int) $order->get_id(),
					'status'      => $status,
					'qty'         => (int) $item->get_quantity(),
					'line_total'  => $line_total,
					'amount_paid' => $paid,
					'date'        => $date,
				);
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `composer test`
Expected: PASS — the new collection test passes; the existing LedgerCalculator/PaymentCalculator tests still pass (16 total). Also run `php -l src/Data/OrderRepository.php src/Domain/LedgerRow.php src/Domain/LedgerCalculator.php` → all "No syntax errors detected".

- [ ] **Step 7: Commit**

```bash
git add src/Data/OrderRepository.php src/Domain/LedgerRow.php src/Domain/LedgerCalculator.php tests/Domain/LedgerCalculatorTest.php
git commit -m "feat: carry distinct order ids from records into ledger rows"
```

---

### Task 3: OrderRepository write methods (markPaid, addPayment)

**Files:**
- Modify: `src/Data/OrderRepository.php`

**Interfaces:**
- Consumes: `OrillaEagles\Ledger\Domain\PaymentCalculator`.
- Produces:
  - `markPaid(int $orderId): void` — sets `_tml_amount_paid` to the order total and status to `completed`. Throws `\RuntimeException` if the order is missing.
  - `addPayment(int $orderId, float $increment): void` — reads current `_tml_amount_paid`, runs `PaymentCalculator::apply` against the order total, stores `new_paid`, and sets status `completed` when `should_complete`. Throws `\RuntimeException` if the order is missing.

- [ ] **Step 1: Add the `use` import**

In `src/Data/OrderRepository.php`, next to the existing `use OrillaEagles\Ledger\Status\OrderStatus;` line, add:

```php
use OrillaEagles\Ledger\Domain\PaymentCalculator;
```

- [ ] **Step 2: Add the two methods**

Add these methods to the `OrderRepository` class (e.g. after `createRequestedOrder()`):

```php
	public function markPaid( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found: ' . $order_id );
		}
		$order->update_meta_data( self::AMOUNT_PAID_META, (float) $order->get_total() );
		$order->save();
		$order->update_status( 'completed', __( 'Marked paid in Membership Ledger.', 'team-membership-ledger' ) );
	}

	public function addPayment( int $order_id, float $increment ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new \RuntimeException( 'Order not found: ' . $order_id );
		}
		$current = (float) $order->get_meta( self::AMOUNT_PAID_META );
		$total   = (float) $order->get_total();
		$result  = PaymentCalculator::apply( $current, $increment, $total );

		$order->update_meta_data( self::AMOUNT_PAID_META, $result['new_paid'] );
		$order->save();

		if ( $result['should_complete'] ) {
			$order->update_status( 'completed', __( 'Payment completed in Membership Ledger.', 'team-membership-ledger' ) );
		}
	}
```

- [ ] **Step 3: Verify in the running site (WP-CLI)**

`php -l src/Data/OrderRepository.php` → no syntax errors. Then, creating and cleaning up throwaway data (prefer `wp eval-file` with a temp script you delete):
1. Create a throwaway $200 product and an active throwaway customer; `createRequestedOrder($cust, $prod)` → order id, status `requested`.
2. `addPayment($orderId, 50)` → reload order: `_tml_amount_paid` meta == 50, status still `requested`.
3. `addPayment($orderId, 30)` → meta == 80, status still `requested`.
4. `addPayment($orderId, 200)` → meta == 280, status now `completed` (overpayment completes).
5. On a second throwaway order, `markPaid($orderId2)` → meta == 200 (full total), status `completed`.
6. Delete the throwaway orders, product, customer; confirm gone.

(Expect harmless SQLite "datatype mismatch" noise from WooCommerce core refund-cache priming — known/accepted; the meta/status changes still apply correctly.)

Run `composer test` → 16 tests still pass.

- [ ] **Step 4: Commit**

```bash
git add src/Data/OrderRepository.php
git commit -m "feat: markPaid and addPayment write methods on OrderRepository"
```

---

### Task 4: Ledger screen inline actions + POST handler

**Files:**
- Modify: `src/Admin/LedgerScreen.php`
- Modify: `src/Plugin.php` (register `admin_init` -> `LedgerScreen::handlePost`)

**Interfaces:**
- Consumes: `OrderRepository` (`markPaid`, `addPayment`, `createRequestedOrder`), `RosterRepository`, `LedgerCalculator`, `MemberStatus`, `LedgerRow`.
- Produces: `LedgerScreen::handlePost(): void` and an "Actions" column in the table. Actions: `mark_paid`, `add_payment`. Targets an existing `order_id`, or creates the order on the fly from `member_id` + `product_id`. Redirects back to `?page=tml-ledger&product_id=<pid>`.

- [ ] **Step 1: Add the `use` for LedgerRow**

In `src/Admin/LedgerScreen.php`, add next to the other `use` statements:

```php
use OrillaEagles\Ledger\Domain\LedgerRow;
```

- [ ] **Step 2: Add the handlePost method**

Add this method to the `LedgerScreen` class:

```php
	public static function handlePost(): void {
		if ( empty( $_POST['tml_ledger_action'] ) ) {
			return;
		}
		if ( ! current_user_can( Menu::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'team-membership-ledger' ) );
		}
		check_admin_referer( 'tml_ledger' );

		$action     = sanitize_key( wp_unslash( $_POST['tml_ledger_action'] ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$order_id   = absint( $_POST['order_id'] ?? 0 );
		$member_id  = absint( $_POST['member_id'] ?? 0 );

		$orders = new OrderRepository();

		try {
			// Resolve the target order: an existing one, or create it on the fly.
			if ( ! $order_id ) {
				if ( ! $member_id || ! $product_id ) {
					throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
				}
				$order_id = $orders->createRequestedOrder( $member_id, $product_id );
			}

			if ( 'mark_paid' === $action ) {
				$orders->markPaid( $order_id );
				$msg = __( 'Marked paid.', 'team-membership-ledger' );
			} elseif ( 'add_payment' === $action ) {
				$amount = round( (float) wp_unslash( $_POST['amount'] ?? 0 ), 2 );
				if ( $amount <= 0 ) {
					throw new \RuntimeException( __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ) );
				}
				$orders->addPayment( $order_id, $amount );
				$msg = __( 'Payment recorded.', 'team-membership-ledger' );
			} else {
				throw new \RuntimeException( __( 'Unknown action.', 'team-membership-ledger' ) );
			}

			set_transient( 'tml_ledger_notice', $msg, 30 );
		} catch ( \RuntimeException $e ) {
			set_transient( 'tml_ledger_error', $e->getMessage(), 30 );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'tml-ledger', 'product_id' => $product_id ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
```

- [ ] **Step 3: Add the per-row actions helper**

Add this private method to the `LedgerScreen` class:

```php
	private static function actions( LedgerRow $row, int $product_id ): string {
		if ( MemberStatus::PAID === $row->status() ) {
			return '';
		}
		if ( $row->orderCount() > 1 ) {
			return esc_html__( 'Multiple orders — manage in WooCommerce', 'team-membership-ledger' );
		}

		$order_id = $row->singleOrderId();
		ob_start();
		?>
		<form method="post" style="display:inline-block;margin:0 .5em .25em 0">
			<?php wp_nonce_field( 'tml_ledger' ); ?>
			<input type="hidden" name="tml_ledger_action" value="mark_paid" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
			<?php if ( $order_id ) : ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
			<?php else : ?>
				<input type="hidden" name="member_id" value="<?php echo esc_attr( $row->memberId() ); ?>" />
			<?php endif; ?>
			<button class="button button-primary"><?php esc_html_e( 'Mark Paid', 'team-membership-ledger' ); ?></button>
		</form>
		<form method="post" style="display:inline-block;margin:0">
			<?php wp_nonce_field( 'tml_ledger' ); ?>
			<input type="hidden" name="tml_ledger_action" value="add_payment" />
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" />
			<?php if ( $order_id ) : ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
			<?php else : ?>
				<input type="hidden" name="member_id" value="<?php echo esc_attr( $row->memberId() ); ?>" />
			<?php endif; ?>
			<input type="number" step="0.01" min="0.01" name="amount" placeholder="<?php esc_attr_e( 'Amount', 'team-membership-ledger' ); ?>" style="width:6em" />
			<button class="button"><?php esc_html_e( 'Add', 'team-membership-ledger' ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}
```

- [ ] **Step 4: Show notices + add the Actions column in render()**

In `render()`, immediately after the `<h1>...Ledger...</h1>` line, add the notice output:

```php
			<?php
			$notice = get_transient( 'tml_ledger_notice' );
			$error  = get_transient( 'tml_ledger_error' );
			delete_transient( 'tml_ledger_notice' );
			delete_transient( 'tml_ledger_error' );
			if ( $notice ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
			}
			if ( $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
			?>
```

In the table `<thead>` row, add a new header cell after the Date header:

```php
					<th><?php esc_html_e( 'Actions', 'team-membership-ledger' ); ?></th>
```

In the table body row, after the Date `<td>`, add the actions cell (the helper returns already-escaped HTML, so echo it directly):

```php
						<td><?php echo self::actions( $row, $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaping internally ?></td>
```

- [ ] **Step 5: Wire handlePost into Plugin::boot()**

In `src/Plugin.php` `boot()`, add on `admin_init` (alongside the existing Roster/Rollover handlers):

```php
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\LedgerScreen::class, 'handlePost' ) );
```

- [ ] **Step 6: Verify in the running site (WP-CLI + reasoning)**

`php -l src/Admin/LedgerScreen.php src/Plugin.php` → no syntax errors. Then, with throwaway data (product $200, two active members A and B, cleaned up after):
1. Simulate Mark Paid for member A via handlePost with `$_POST = ['tml_ledger_action'=>'mark_paid','product_id'=>PID,'member_id'=>A_ID,'_wpnonce'=>wp_create_nonce('tml_ledger')]` (no order_id → on-the-fly create). Confirm: an order now exists for A on that product, status `completed`. (handlePost ends in redirect+exit — drive it in a subshell or verify state in a separate `wp eval`.)
2. Simulate Add payment $50 for member B (on-the-fly): confirm an order is created, `_tml_amount_paid` == 50, status `requested`.
3. Render the screen for the product (`$_GET['product_id']=PID; LedgerScreen::render();`) and confirm the HTML contains: A row showing "Paid" with no action buttons; B row showing "Owes" balance 150 with a "Mark Paid" button and an amount input; a "Not entered" active member still showing "Mark Paid"/"Add" controls.
4. Delete throwaway orders/product/members; confirm gone.

Run `composer test` → 16 tests still pass.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/LedgerScreen.php src/Plugin.php
git commit -m "feat: inline Mark Paid and Add payment actions on the Ledger screen"
```

---

### Task 5: Document inline payments in the README

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update the Ledger and payments sections**

In `README.md`, under the daily-use **Ledger** bullet, append that you can now record payments inline; and replace the "Recording payments" section so it describes the Ledger-first flow. Add this content (adjust surrounding wording to fit the existing section headers):

```markdown
- **Membership → Ledger** — pick a product to see every active member's status
  (Paid / Owes / Not entered), and record payments inline:
  - **Mark Paid** — marks that member's order Completed (paid in full). On a
    "Not entered" member it creates the charge first, then marks it paid.
  - **Add payment** — type the amount just received and click Add; it accrues
    toward the total (e.g. $50 then $30 on a $200 charge → "$80 of $200"). When
    the running total reaches the order total the order auto-completes. On a
    "Not entered" member it creates the charge first, then records the payment.
  - A member with multiple orders for one product shows a note to manage it in
    WooCommerce (inline actions are hidden to avoid targeting the wrong order).

## Recording payments (details)

- An order's **status is the paid flag**: *Requested* = owes, *Completed* = paid.
- The Ledger's **Add payment** stores the running total in the order meta
  `_tml_amount_paid` and auto-completes the order when it reaches the total.
- To reverse a payment or mark an order back to unpaid, edit the order in
  **WooCommerce → Orders**.
```

- [ ] **Step 2: Verify + commit**

Confirm the README renders sensibly and `composer test` is still green (16 tests). Then:

```bash
git add README.md
git commit -m "docs: document inline payment actions on the Ledger"
```

---

## Self-review notes

- **Spec coverage:** Mark Paid = full Completed (Task 3 `markPaid`, Task 4 button); incremental Add payment with auto-complete (Task 1 `PaymentCalculator`, Task 3 `addPayment`, Task 4 form); create-on-the-fly for not-entered (Task 4 handlePost resolves order via `createRequestedOrder`); multi-order rows hide actions (Task 2 `orderCount`/`singleOrderId`, Task 4 `actions()`); security cap+nonce+sanitize+PRG (Task 4); README (Task 5). All design points map to tasks.
- **Type consistency:** `productRecords()` now emits `order_id` (Task 2) consumed by `LedgerCalculator` (Task 2) into `LedgerRow::orderIds()`; `singleOrderId()` feeds the Task 4 forms; `PaymentCalculator::apply` return keys `new_paid`/`should_complete` used identically in Task 1 tests and Task 3 `addPayment`.
- **Backward compatibility:** `LedgerRow`'s new `order_ids` param is trailing with an `array()` default, so the not-entered `LedgerRow` construction and any other caller stay valid; existing 9 tests untouched by signature change.
```
