# Ledger DataViews Rebuild — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the PHP-rendered, full-page-POST admin Ledger screen with a React `@wordpress/dataviews` UI backed by REST endpoints, preserving inline payment updating (add partial payment, mark paid, on-the-fly order creation) and its security model.

**Architecture:** A thin REST controller (`tml/v1`) adapts the existing, unit-tested domain layer (`LedgerCalculator`, `PaymentCalculator`, `OrderRepository`) — nothing in `src/Domain` or `src/Data` changes behavior. The Ledger admin page becomes a mount point for a React app that reads rows from `GET /ledger` and writes payments to `POST /ledger/add-payment` and `POST /ledger/mark-paid`, then refetches the affected row. DataViews does sorting/filtering/pagination client-side.

**Tech Stack:** PHP 7.4+, WooCommerce (HPOS), WordPress REST API, `@wordpress/scripts` (webpack/Babel), `@wordpress/dataviews`, `@wordpress/components`, `@wordpress/api-fetch`, `@wordpress/element`, `@wordpress/i18n`. Studio CLI (`studio wp …`) for verification.

## Global Constraints

- **Namespace/PSR-4:** `OrillaEagles\Ledger\` → `src/`; tests `OrillaEagles\Ledger\Tests\` → `tests/`. Text domain: `team-membership-ledger`.
- **Capability:** every REST route's `permission_callback` requires `Menu::CAP` = `manage_woocommerce`.
- **Nonce:** REST calls authenticate with the standard `wp_rest` nonce; `@wordpress/api-fetch` in wp-admin is preconfigured with the site root + REST nonce (via the `wp-api-fetch` handle's inline script), so no manual nonce middleware is needed.
- **DataViews is BUNDLED, not externalized (spike-proven).** This site's Gutenberg (23.6) compiles DataViews into its editor bundles only and does NOT register a `wp-dataviews` script handle. So the build bundles `@wordpress/dataviews` (pinned to `17.2.0`, the version GB 23.6 ships) and externalizes everything else it imports (`@wordpress/components`, `data`, `element`, `i18n`, `private-apis`, `compose`, `primitives`, `a11y`, `date`, `keycodes`, `rich-text`, `warning`, plus `react`/`react-dom`) to the host's registered handles — all confirmed present. A custom `webpack.config.js` does this via `requestToExternal` returning `null` for `@wordpress/dataviews*` (bundle) and `undefined` otherwise (default externalize). A `resolve.alias` forces `@wordpress/icons` to its CJS entry because `@wordpress/icons@15.2.0` ships a broken `exports` field (missing `build-module/index.mjs`). The DataViews stylesheet is imported in `src/index.js`; because `@wordpress/dataviews` declares `sideEffects:false`, a webpack `module.rules` entry flags that CSS as having side effects so wp-scripts extracts it to `build/style-index.css` (+ `style-index-rtl.css`). There is NO `wp-dataviews` style handle on this site, so `enqueue()` ships our own copy: `wp_enqueue_style('tml-ledger-app', build/style-index.css, ['wp-components'])` (with `wp_style_add_data(..., 'rtl', 'replace')`), plus `wp_enqueue_style('wp-components')` for Modal/Button/SelectControl chrome. Do NOT enqueue `wp-dataviews`.
- **Sanitize:** `absint` for all ids; `amount` is `round( (float) …, 2 )` and must be `> 0`. These mirror the retiring `LedgerScreen::handlePost()` exactly.
- **Payment semantics (unchanged):** `amount` on add-payment is the increment "received now", ADDED to `_tml_amount_paid`; the order auto-completes when the running total reaches the order total (`PaymentCalculator::apply`). No editing down, no refund/undo from the Ledger.
- **Eligibility rules:** payment actions are hidden on `paid` rows and on rows with `orderCount > 1` (the "multiple orders — manage in WooCommerce" read-only state).
- **WordPress floor:** DataViews is bundled (see above), so the host need not register `wp-dataviews`. Bump the plugin header `Requires at least` to `6.7` (the floor for the externalized `@wordpress/components`/`data` APIs DataViews 17.2.0 expects).
- **Build outputs** live in `build/` (git-ignored); source in `src/` (`.js`) alongside the existing `src/` PHP. JS entry is `src/index.js`. No `block.json` — this is an admin script, not a block.
- **Scope of THIS plan:** Ledger only (design Phases 1–2). Roster (Phase 3) is a separate follow-up plan on the same plumbing.

---

## File Structure

**New (PHP):**
- `src/Rest/LedgerController.php` — registers `tml/v1` routes; read + two write callbacks; pure-ish adapters over the domain layer.
- `src/Domain/LedgerSerializer.php` — pure `LedgerRow` → array mapper (unit-tested).
- `tests/Domain/LedgerSerializerTest.php` — unit test for the serializer.

**New (JS/build):**
- `package.json` — `@wordpress/scripts` build.
- `src/index.js` — mounts the React app on `#tml-ledger-root`.
- `src/App.js` — product selector + `<DataViews>`, fetch + client-side view state + notices.
- `src/fields.js` — DataViews field definitions (factory taking `currency`).
- `src/actions.js` — DataViews actions (add-payment modal, mark-paid) factory.
- `src/format.js` — pure `formatMoney()` (unit-tested).
- `src/api.js` — apiFetch wrappers: `fetchLedger`, `addPayment`, `markPaid`.
- `src/format.test.js` — jest unit test for `formatMoney`.

**Modified:**
- `team-membership-ledger.php` — bump `Requires at least: 6.7`.
- `src/Plugin.php` — register `rest_api_init` + `admin_enqueue_scripts`; (Task 7) drop the `LedgerScreen::handlePost` `admin_init` hook.
- `src/Admin/LedgerScreen.php` — add `enqueue()` + a mount `<div>` (Task 1); (Task 7) strip the PHP table, `actions()`, `label()`, `handlePost()`.
- `.gitignore` — add `build/` and `node_modules/`.

---

## PHASE 1 — TRACER BULLET

### Task 1: Build tooling + enqueue scaffold

**Files:**
- Create: `plugins/team-membership-ledger/package.json`
- Create: `plugins/team-membership-ledger/src/index.js`
- Modify: `plugins/team-membership-ledger/src/Admin/LedgerScreen.php` (add `enqueue()`; add mount div in `render()`)
- Modify: `plugins/team-membership-ledger/src/Plugin.php` (hook `admin_enqueue_scripts`)
- Modify: `plugins/team-membership-ledger/team-membership-ledger.php` (`Requires at least: 6.7`)
- Modify: `plugins/team-membership-ledger/.gitignore`

> **AS-BUILT (spike-adjusted).** A spike established that this site does not
> expose a `wp-dataviews` handle, so DataViews is BUNDLED via a custom
> `webpack.config.js` (see Global Constraints). The steps below reflect what was
> implemented and verified in the browser. Also created: `webpack.config.js`.

**Interfaces:**
- Produces: a global `window.tmlLedger = { productId: number }`; a DOM node `#tml-ledger-root` on the Ledger admin page; `wp-components` style enqueued (DataViews styles are bundled into the JS, not a handle).

- [ ] **Step 1: Create `package.json`** (pins `@wordpress/dataviews` so it can be bundled)

```json
{
  "name": "team-membership-ledger",
  "private": true,
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "test:js": "wp-scripts test-unit-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^30.0.0"
  },
  "dependencies": {
    "@wordpress/dataviews": "17.2.0"
  }
}
```

- [ ] **Step 2: Create `webpack.config.js`** (bundle DataViews, externalize the rest, alias the broken icons ESM)

```js
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve && defaultConfig.resolve.alias ),
			// @wordpress/icons@15.2.0 ships a broken `exports` field (missing
			// build-module/index.mjs); force its working CJS entry.
			'@wordpress/icons$': path.resolve(
				__dirname,
				'node_modules/@wordpress/icons/build/index.cjs'
			),
		},
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if ( request === '@wordpress/dataviews' ) {
					return null; // bundle
				}
				if ( request.startsWith( '@wordpress/dataviews/' ) ) {
					return null; // bundle its stylesheet import too
				}
				return undefined; // default externalization for all others
			},
		} ),
	],
};
```

- [ ] **Step 3: Create a minimal `src/index.js` mount check**

```js
import { createRoot } from '@wordpress/element';

const el = document.getElementById( 'tml-ledger-root' );
if ( el ) {
	createRoot( el ).render( 'Ledger app mounted.' );
}
```

- [ ] **Step 4: Install deps and build**

Run: `cd plugins/team-membership-ledger && npm install && npm run build`
Expected: `build/index.js` and `build/index.asset.php` are created; no errors.

- [ ] **Step 5: Add `enqueue()` to `LedgerScreen`**

Add this static method to `src/Admin/LedgerScreen.php`:

```php
	public static function enqueue( string $hook ): void {
		// Submenu hook suffix contains the page slug for the Ledger screen.
		if ( strpos( $hook, 'tml-ledger' ) === false ) {
			return;
		}
		$asset_file = TML_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'tml-ledger-app',
			plugins_url( 'build/index.js', TML_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'tml-ledger-app', 'team-membership-ledger' );
		// DataViews styles are bundled into build/index.js; host component styles
		// are still needed for Modal/Button/SelectControl chrome. There is no
		// wp-dataviews style handle on this site (see Global Constraints).
		wp_enqueue_style( 'wp-components' );

		wp_localize_script(
			'tml-ledger-app',
			'tmlLedger',
			array(
				'productId' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}
```

- [ ] **Step 6: Add the mount div to `render()`**

In `src/Admin/LedgerScreen.php::render()`, immediately after the `<h1>…Ledger…</h1>` line, add the root node (leave the existing product form + table in place for now — they are removed in Task 7):

```php
			<div id="tml-ledger-root"></div>
```

- [ ] **Step 7: Hook the enqueue in `Plugin::boot()`**

In `src/Plugin.php::boot()`, after the existing `admin_menu` line, add:

```php
		add_action( 'admin_enqueue_scripts', array( \OrillaEagles\Ledger\Admin\LedgerScreen::class, 'enqueue' ) );
```

- [ ] **Step 8: Bump the WP floor and ignore build artifacts**

In `team-membership-ledger.php` change `Requires at least: 6.5` to `Requires at least: 6.7`.

In `.gitignore` add (if not present):

```
/build/
/node_modules/
```

- [ ] **Step 9: Verify in the browser**

Ensure the site is running (`studio start --skip-browser`), open **Membership → Ledger** in wp-admin. Expected: the text "Ledger app mounted." appears under the heading (the old table still shows below it); no console errors.

- [ ] **Step 10: Commit**

```bash
cd plugins/team-membership-ledger
git add package.json package-lock.json src/index.js src/Admin/LedgerScreen.php src/Plugin.php team-membership-ledger.php .gitignore
git commit -m "build: scaffold DataViews app tooling and enqueue on Ledger screen"
```

---

### Task 2: LedgerSerializer (pure) + unit test

**Files:**
- Create: `plugins/team-membership-ledger/src/Domain/LedgerSerializer.php`
- Test: `plugins/team-membership-ledger/tests/Domain/LedgerSerializerTest.php`

**Interfaces:**
- Consumes: `LedgerRow` (existing).
- Produces: `LedgerSerializer::row( LedgerRow ): array` and `LedgerSerializer::rows( LedgerRow[] ): array`. Row shape: `{ memberId:int, name:string, email:string, status:string, qty:int, total:float, paid:float, balance:float, date:?string, orderId:?int, orderCount:int }`. `memberId` is the DataViews record id.

- [ ] **Step 1: Write the failing test**

Create `tests/Domain/LedgerSerializerTest.php`:

```php
<?php
namespace OrillaEagles\Ledger\Tests\Domain;

use OrillaEagles\Ledger\Domain\LedgerRow;
use OrillaEagles\Ledger\Domain\LedgerSerializer;
use OrillaEagles\Ledger\Domain\MemberStatus;
use PHPUnit\Framework\TestCase;

final class LedgerSerializerTest extends TestCase {

	public function test_serializes_a_single_order_row(): void {
		$row = new LedgerRow( 7, 'Jane Doe', 'jane@example.com', MemberStatus::OWES, 1, 200.0, 80.0, '2026-07-24 10:00:00', array( 55 ) );
		$out = LedgerSerializer::row( $row );

		$this->assertSame( 7, $out['memberId'] );
		$this->assertSame( 'Jane Doe', $out['name'] );
		$this->assertSame( 'jane@example.com', $out['email'] );
		$this->assertSame( MemberStatus::OWES, $out['status'] );
		$this->assertSame( 1, $out['qty'] );
		$this->assertSame( 200.0, $out['total'] );
		$this->assertSame( 80.0, $out['paid'] );
		$this->assertSame( 120.0, $out['balance'] );
		$this->assertSame( '2026-07-24', $out['date'] );
		$this->assertSame( 55, $out['orderId'] );
		$this->assertSame( 1, $out['orderCount'] );
	}

	public function test_null_date_and_multi_order_have_null_order_id(): void {
		$row = new LedgerRow( 9, 'No Orders', 'n@example.com', MemberStatus::NOT_ENTERED, 0, 0.0, 0.0, null, array() );
		$out = LedgerSerializer::row( $row );
		$this->assertNull( $out['date'] );
		$this->assertNull( $out['orderId'] );
		$this->assertSame( 0, $out['orderCount'] );

		$multi = new LedgerRow( 3, 'Two Orders', 't@example.com', MemberStatus::OWES, 2, 400.0, 0.0, '2026-01-01 00:00:00', array( 1, 2 ) );
		$this->assertNull( LedgerSerializer::row( $multi )['orderId'] );
		$this->assertSame( 2, LedgerSerializer::row( $multi )['orderCount'] );
	}

	public function test_rows_maps_a_list(): void {
		$rows = array(
			new LedgerRow( 1, 'A', 'a@e.com', MemberStatus::PAID, 1, 10.0, 10.0, null, array( 4 ) ),
			new LedgerRow( 2, 'B', 'b@e.com', MemberStatus::OWES, 1, 10.0, 0.0, null, array( 5 ) ),
		);
		$out = LedgerSerializer::rows( $rows );
		$this->assertCount( 2, $out );
		$this->assertSame( 1, $out[0]['memberId'] );
		$this->assertSame( 2, $out[1]['memberId'] );
	}
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd plugins/team-membership-ledger && composer test`
Expected: FAIL — `Class "OrillaEagles\Ledger\Domain\LedgerSerializer" not found`.

- [ ] **Step 3: Implement `LedgerSerializer`**

Create `src/Domain/LedgerSerializer.php`:

```php
<?php
namespace OrillaEagles\Ledger\Domain;

defined( 'ABSPATH' ) || defined( 'PHPUNIT_COMPOSER_INSTALL' ) || exit;

final class LedgerSerializer {

	/**
	 * @param LedgerRow[] $rows
	 * @return array<int,array<string,mixed>>
	 */
	public static function rows( array $rows ): array {
		return array_map( array( self::class, 'row' ), $rows );
	}

	/** @return array<string,mixed> */
	public static function row( LedgerRow $row ): array {
		return array(
			'memberId'   => $row->memberId(),
			'name'       => $row->name(),
			'email'      => $row->email(),
			'status'     => $row->status(),
			'qty'        => $row->qty(),
			'total'      => $row->total(),
			'paid'       => $row->paid(),
			'balance'    => $row->balance(),
			'date'       => $row->date() ? substr( $row->date(), 0, 10 ) : null,
			'orderId'    => $row->singleOrderId(),
			'orderCount' => $row->orderCount(),
		);
	}
}
```

Note: the `defined( … ) || exit` guard permits the class to load under PHPUnit (no `ABSPATH`) while still blocking direct web access in production.

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd plugins/team-membership-ledger && composer test`
Expected: PASS (all `LedgerSerializerTest` + existing `PaymentCalculatorTest`, `LedgerCalculatorTest`, `RolloverPlanTest`).

- [ ] **Step 5: Commit**

```bash
cd plugins/team-membership-ledger
git add src/Domain/LedgerSerializer.php tests/Domain/LedgerSerializerTest.php
git commit -m "feat: add pure LedgerRow serializer for REST output"
```

---

### Task 3: REST read route `GET /ledger`

**Files:**
- Create: `plugins/team-membership-ledger/src/Rest/LedgerController.php`
- Modify: `plugins/team-membership-ledger/src/Plugin.php` (hook `rest_api_init`)

**Interfaces:**
- Consumes: `LedgerSerializer::rows()`, `LedgerCalculator::forProduct()`, `OrderRepository` (`productRecords`, `sellableProducts`), `RosterRepository::activeMembers()`, `Menu::CAP`.
- Produces: `LedgerController::NAMESPACE = 'tml/v1'`; route `GET tml/v1/ledger?product_id=…` returning `{ rows: array, products: [{id,name}], currency: {symbol,decimals} }`. `LedgerController::can_manage(): bool`. Later tasks add write routes to this class.

- [ ] **Step 1: Create the controller with the read route**

Create `src/Rest/LedgerController.php`:

```php
<?php
namespace OrillaEagles\Ledger\Rest;

use OrillaEagles\Ledger\Admin\Menu;
use OrillaEagles\Ledger\Data\OrderRepository;
use OrillaEagles\Ledger\Data\RosterRepository;
use OrillaEagles\Ledger\Domain\LedgerCalculator;
use OrillaEagles\Ledger\Domain\LedgerSerializer;

defined( 'ABSPATH' ) || exit;

final class LedgerController {

	public const NAMESPACE = 'tml/v1';

	public static function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/ledger',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_ledger' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( Menu::CAP );
	}

	public static function get_ledger( \WP_REST_Request $request ): \WP_REST_Response {
		$product_id = absint( $request['product_id'] );
		$orders     = new OrderRepository();
		$roster     = new RosterRepository();

		$rows = array();
		if ( $product_id ) {
			$members = $roster->activeMembers();
			$records = $orders->productRecords( $product_id );
			$rows    = LedgerSerializer::rows( LedgerCalculator::forProduct( $members, $records ) );
		}

		return new \WP_REST_Response(
			array(
				'rows'     => $rows,
				'products' => $orders->sellableProducts(),
				'currency' => self::currency(),
			),
			200
		);
	}

	private static function currency(): array {
		return array(
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
			'decimals' => wc_get_price_decimals(),
		);
	}
}
```

- [ ] **Step 2: Register the routes in `Plugin::boot()`**

In `src/Plugin.php::boot()`, after the `admin_enqueue_scripts` line from Task 1, add:

```php
		add_action( 'rest_api_init', array( \OrillaEagles\Ledger\Rest\LedgerController::class, 'register' ) );
```

- [ ] **Step 3: Smoke-test the callback with no fatals (empty product)**

Run:
```bash
studio wp eval '$r = new WP_REST_Request("GET","/tml/v1/ledger"); $r->set_param("product_id", 0); echo wp_json_encode( \OrillaEagles\Ledger\Rest\LedgerController::get_ledger($r)->get_data() );'
```
Expected: JSON with `"rows":[]`, a non-empty `"products"` array (your published products), and a `"currency"` object. No PHP errors.

- [ ] **Step 4: Smoke-test with a real product id**

Pick a product id from the previous output, then:
```bash
studio wp eval '$r = new WP_REST_Request("GET","/tml/v1/ledger"); $r->set_param("product_id", <PRODUCT_ID>); echo wp_json_encode( \OrillaEagles\Ledger\Rest\LedgerController::get_ledger($r)->get_data() );'
```
Expected: `"rows"` contains one entry per active member with the serialized shape from Task 2 (memberId, status, total, paid, balance, …).

- [ ] **Step 5: Verify the route is registered and permission-gated**

Run: `studio wp eval 'echo array_key_exists("/tml/v1/ledger", rest_get_server()->get_routes()) ? "route-ok" : "missing";'`
Expected: `route-ok`.

- [ ] **Step 6: Commit**

```bash
cd plugins/team-membership-ledger
git add src/Rest/LedgerController.php src/Plugin.php
git commit -m "feat: add tml/v1 REST read route for the ledger"
```

---

### Task 4: DataViews read-only render

**Files:**
- Create: `plugins/team-membership-ledger/src/format.js`
- Create: `plugins/team-membership-ledger/src/format.test.js`
- Create: `plugins/team-membership-ledger/src/api.js`
- Create: `plugins/team-membership-ledger/src/fields.js`
- Create: `plugins/team-membership-ledger/src/App.js`
- Modify: `plugins/team-membership-ledger/src/index.js` (render `<App/>`)

**Interfaces:**
- Consumes: `GET tml/v1/ledger`; `window.tmlLedger.productId`.
- Produces: `formatMoney( amount, currency )` (pure); `fetchLedger( productId )`; `makeFields( currency )`; `<App/>`. Row records use `memberId` as id.

- [ ] **Step 1: Write the failing JS unit test for `formatMoney`**

Create `src/format.test.js`:

```js
import { formatMoney } from './format';

describe( 'formatMoney', () => {
	const cad = { symbol: '$', decimals: 2 };

	it( 'formats a whole number with symbol and decimals', () => {
		expect( formatMoney( 200, cad ) ).toBe( '$200.00' );
	} );

	it( 'formats a fractional amount', () => {
		expect( formatMoney( 80.5, cad ) ).toBe( '$80.50' );
	} );

	it( 'respects a zero-decimal currency', () => {
		expect( formatMoney( 1500, { symbol: '¥', decimals: 0 } ) ).toBe( '¥1500' );
	} );

	it( 'treats non-numeric input as zero', () => {
		expect( formatMoney( null, cad ) ).toBe( '$0.00' );
	} );
} );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `cd plugins/team-membership-ledger && npm run test:js`
Expected: FAIL — cannot find `./format`.

- [ ] **Step 3: Implement `src/format.js`**

```js
/**
 * Format a numeric amount with a currency symbol and fixed decimals.
 *
 * @param {number} amount   Raw amount.
 * @param {{symbol:string, decimals:number}} currency Currency config.
 * @return {string} Formatted money string.
 */
export function formatMoney( amount, currency ) {
	const value = Number.isFinite( Number( amount ) ) ? Number( amount ) : 0;
	const decimals = Number.isFinite( Number( currency?.decimals ) ) ? Number( currency.decimals ) : 2;
	return `${ currency?.symbol ?? '' }${ value.toFixed( decimals ) }`;
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `cd plugins/team-membership-ledger && npm run test:js`
Expected: PASS.

- [ ] **Step 5: Create `src/api.js`**

```js
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch the ledger payload for a product.
 *
 * @param {number} productId Selected product id (0 = none).
 * @return {Promise<{rows:Array, products:Array, currency:Object}>} Ledger payload.
 */
export function fetchLedger( productId ) {
	return apiFetch( {
		path: `/tml/v1/ledger?product_id=${ encodeURIComponent( productId ) }`,
	} );
}
```

- [ ] **Step 6: Create `src/fields.js`**

```js
import { __ } from '@wordpress/i18n';
import { formatMoney } from './format';

const STATUS_LABELS = {
	paid: __( 'Paid', 'team-membership-ledger' ),
	owes: __( 'Owes', 'team-membership-ledger' ),
	not_entered: __( 'Not entered', 'team-membership-ledger' ),
};

/**
 * Build the DataViews field definitions.
 *
 * @param {{symbol:string, decimals:number}} currency Currency config.
 * @return {Array} Field definitions.
 */
export function makeFields( currency ) {
	const money = ( getValue ) => ( { item } ) => formatMoney( getValue( { item } ), currency );

	return [
		{
			id: 'name',
			label: __( 'Member', 'team-membership-ledger' ),
			enableGlobalSearch: true,
			getValue: ( { item } ) => item.name,
			render: ( { item } ) => item.name,
		},
		{
			id: 'status',
			label: __( 'Status', 'team-membership-ledger' ),
			elements: Object.entries( STATUS_LABELS ).map( ( [ value, label ] ) => ( { value, label } ) ),
			getValue: ( { item } ) => item.status,
			render: ( { item } ) => {
				const label = STATUS_LABELS[ item.status ] ?? item.status;
				return item.orderCount > 1
					? `${ label } — ${ __( 'multiple orders, manage in WooCommerce', 'team-membership-ledger' ) }`
					: label;
			},
		},
		{
			id: 'qty',
			label: __( 'Qty', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.qty,
		},
		{
			id: 'total',
			label: __( 'Total', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.total,
			render: money( ( { item } ) => item.total ),
		},
		{
			id: 'paid',
			label: __( 'Paid', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.paid,
			render: money( ( { item } ) => item.paid ),
		},
		{
			id: 'balance',
			label: __( 'Balance', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.balance,
			render: money( ( { item } ) => item.balance ),
		},
		{
			id: 'date',
			label: __( 'Date', 'team-membership-ledger' ),
			getValue: ( { item } ) => item.date ?? '',
			render: ( { item } ) => item.date ?? '—',
		},
	];
}
```

- [ ] **Step 7: Create `src/App.js` (read-only DataViews)**

```js
import { useState, useEffect, useMemo } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { fetchLedger } from './api';
import { makeFields } from './fields';

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	perPage: 25,
	search: '',
	filters: [],
	sort: { field: 'name', direction: 'asc' },
	fields: [ 'status', 'qty', 'total', 'paid', 'balance', 'date' ],
	titleField: 'name',
};

export default function App() {
	const [ productId, setProductId ] = useState( window.tmlLedger?.productId || 0 );
	const [ products, setProducts ] = useState( [] );
	const [ currency, setCurrency ] = useState( { symbol: '$', decimals: 2 } );
	const [ rows, setRows ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ view, setView ] = useState( DEFAULT_VIEW );

	useEffect( () => {
		let active = true;
		setIsLoading( true );
		fetchLedger( productId )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setProducts( data.products );
				setCurrency( data.currency );
				setRows( data.rows );
			} )
			.finally( () => active && setIsLoading( false ) );
		return () => {
			active = false;
		};
	}, [ productId ] );

	const fields = useMemo( () => makeFields( currency ), [ currency ] );
	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( rows, view, fields ),
		[ rows, view, fields ]
	);

	const productOptions = [
		{ value: 0, label: __( '— Select a product —', 'team-membership-ledger' ) },
		...products.map( ( p ) => ( { value: p.id, label: p.name } ) ),
	];

	return (
		<div>
			<SelectControl
				label={ __( 'Product', 'team-membership-ledger' ) }
				value={ productId }
				options={ productOptions }
				onChange={ ( value ) => setProductId( Number( value ) ) }
				__nextHasNoMarginBottom
			/>
			{ productId === 0 ? (
				<p>{ __( 'Select a product to view the ledger.', 'team-membership-ledger' ) }</p>
			) : isLoading ? (
				<Spinner />
			) : (
				<DataViews
					data={ shownData }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					paginationInfo={ paginationInfo }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => String( item.memberId ) }
					isLoading={ isLoading }
				/>
			) }
		</div>
	);
}
```

- [ ] **Step 8: Replace `src/index.js` to render `<App/>`**

The DataViews stylesheet import here is what makes wp-scripts extract the CSS to
`build/style-index.css` (enqueued by `enqueue()`); a webpack `sideEffects` rule
keeps it from being tree-shaken. There is no `wp-dataviews` style handle here.

```js
import '@wordpress/dataviews/build-style/style.css';
import { createRoot } from '@wordpress/element';
import App from './App';

const el = document.getElementById( 'tml-ledger-root' );
if ( el ) {
	createRoot( el ).render( <App /> );
}
```

- [ ] **Step 9: Build**

Run: `cd plugins/team-membership-ledger && npm run build`
Expected: builds cleanly.

- [ ] **Step 10: Verify in the browser**

Open **Membership → Ledger**, pick a product from the new DataViews product selector. Expected: a native DataViews table listing active members with Status/Qty/Total/Paid/Balance/Date; sorting, search, and pagination work; money is formatted; no console errors. (The old PHP table still renders below — removed in Task 7.)

- [ ] **Step 11: Commit**

```bash
cd plugins/team-membership-ledger
git add src/format.js src/format.test.js src/api.js src/fields.js src/App.js src/index.js
git commit -m "feat: render read-only ledger with DataViews"
```

---

### Task 5: Write route `add-payment` + row action (tracer bullet complete)

**Files:**
- Modify: `plugins/team-membership-ledger/src/Rest/LedgerController.php` (add write route + helpers)
- Modify: `plugins/team-membership-ledger/src/api.js` (add `addPayment`)
- Create: `plugins/team-membership-ledger/src/actions.js`
- Modify: `plugins/team-membership-ledger/src/App.js` (wire actions + row patching)

**Interfaces:**
- Consumes: `OrderRepository::addPayment()`, `createRequestedOrder()`; `PaymentCalculator` (indirectly).
- Produces: route `POST tml/v1/ledger/add-payment` (`product_id` req, `amount` req>0, `order_id`|`member_id`) returning the updated serialized row; `LedgerController::resolve_order()` + `row_response()` private helpers; JS `addPayment({ productId, orderId, memberId, amount })`; `makeActions({ productId, onRowUpdated, onNotice })`.

- [ ] **Step 1: Add the write route and helpers to `LedgerController`**

In `src/Rest/LedgerController.php`, add these use-imports at the top (alongside the existing ones):

```php
use OrillaEagles\Ledger\Domain\LedgerRow;
```

Register the route inside `register()` (after the `/ledger` route):

```php
		register_rest_route(
			self::NAMESPACE,
			'/ledger/add-payment',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'add_payment' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'order_id'   => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'member_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'amount'     => array( 'type' => 'number', 'required' => true ),
				),
			)
		);
```

Add the callback and helpers as methods on the class:

```php
	public static function add_payment( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$member_id  = absint( $request['member_id'] );
		$amount     = round( (float) $request['amount'], 2 );

		if ( $amount <= 0 ) {
			return new \WP_Error( 'tml_invalid_amount', __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ), array( 'status' => 400 ) );
		}

		try {
			$order_id = self::resolve_order( $order_id, $member_id, $product_id );
			( new OrderRepository() )->addPayment( $order_id, $amount );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $member_id ?: null, $order_id );
	}

	/** Resolve an existing order id, or create the Requested order on the fly. */
	private static function resolve_order( int $order_id, int $member_id, int $product_id ): int {
		if ( $order_id ) {
			return $order_id;
		}
		if ( ! $member_id || ! $product_id ) {
			throw new \RuntimeException( __( 'No order to act on.', 'team-membership-ledger' ) );
		}
		return ( new OrderRepository() )->createRequestedOrder( $member_id, $product_id );
	}

	/** Recompute and return the single affected member's row after a write. */
	private static function row_response( int $product_id, ?int $member_id, int $order_id ): \WP_REST_Response {
		if ( ! $member_id && $order_id ) {
			$order     = wc_get_order( $order_id );
			$member_id = $order ? (int) $order->get_customer_id() : 0;
		}

		$orders  = new OrderRepository();
		$roster  = new RosterRepository();
		$members = $roster->activeMembers();
		$records = $orders->productRecords( $product_id );
		$rows    = LedgerCalculator::forProduct( $members, $records );

		foreach ( $rows as $row ) {
			if ( $row->memberId() === $member_id ) {
				return new \WP_REST_Response( LedgerSerializer::row( $row ), 200 );
			}
		}
		return new \WP_REST_Response( null, 200 );
	}
```

- [ ] **Step 2: Smoke-test the write route callback**

Using a `member_id` (from the roster) and a `product_id` with no existing order for that member, verify on-the-fly creation + partial payment:
```bash
studio wp eval '$r = new WP_REST_Request("POST","/tml/v1/ledger/add-payment"); $r->set_param("product_id", <PRODUCT_ID>); $r->set_param("member_id", <MEMBER_ID>); $r->set_param("amount", 25); echo wp_json_encode( \OrillaEagles\Ledger\Rest\LedgerController::add_payment($r)->get_data() );'
```
Expected: JSON for that member's row with `paid` = 25.00 and `status` = `owes` (assuming the charge exceeds 25). Re-run and confirm `paid` accumulates (additive).

- [ ] **Step 3: Add `addPayment` to `src/api.js`**

```js
/**
 * Record an additive payment; returns the updated row.
 *
 * @param {{productId:number, orderId:?number, memberId:?number, amount:number}} args Payload.
 * @return {Promise<Object|null>} Updated serialized row, or null.
 */
export function addPayment( { productId, orderId, memberId, amount } ) {
	return apiFetch( {
		path: '/tml/v1/ledger/add-payment',
		method: 'POST',
		data: {
			product_id: productId,
			order_id: orderId || 0,
			member_id: memberId || 0,
			amount,
		},
	} );
}
```

- [ ] **Step 4: Create `src/actions.js` with the add-payment modal**

```js
import { useState } from '@wordpress/element';
import { Modal, TextControl, Button, Flex, FlexItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { addPayment } from './api';

function AddPaymentModal( { item, productId, onRowUpdated, onNotice, closeModal } ) {
	const [ amount, setAmount ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	const submit = async () => {
		const value = Math.round( parseFloat( amount ) * 100 ) / 100;
		if ( ! ( value > 0 ) ) {
			onNotice( { type: 'error', message: __( 'Enter a payment amount greater than zero.', 'team-membership-ledger' ) } );
			return;
		}
		setBusy( true );
		try {
			const updated = await addPayment( {
				productId,
				orderId: item.orderId,
				memberId: item.memberId,
				amount: value,
			} );
			if ( updated ) {
				onRowUpdated( updated );
			}
			onNotice( { type: 'success', message: __( 'Payment recorded.', 'team-membership-ledger' ) } );
			closeModal();
		} catch ( e ) {
			onNotice( { type: 'error', message: e.message || __( 'Payment failed.', 'team-membership-ledger' ) } );
		} finally {
			setBusy( false );
		}
	};

	return (
		<Modal
			title={ sprintf( /* translators: %s: member name */ __( 'Add payment — %s', 'team-membership-ledger' ), item.name ) }
			onRequestClose={ closeModal }
		>
			<TextControl
				label={ __( 'Amount received now', 'team-membership-ledger' ) }
				type="number"
				min="0.01"
				step="0.01"
				value={ amount }
				onChange={ setAmount }
				__nextHasNoMarginBottom
			/>
			<Flex justify="flex-end" style={ { marginTop: '1em' } }>
				<FlexItem>
					<Button variant="tertiary" onClick={ closeModal } disabled={ busy }>
						{ __( 'Cancel', 'team-membership-ledger' ) }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
						{ __( 'Add payment', 'team-membership-ledger' ) }
					</Button>
				</FlexItem>
			</Flex>
		</Modal>
	);
}

/**
 * Build DataViews actions.
 *
 * @param {{productId:number, onRowUpdated:Function, onNotice:Function}} ctx Context.
 * @return {Array} Actions.
 */
export function makeActions( { productId, onRowUpdated, onNotice } ) {
	return [
		{
			id: 'add-payment',
			label: __( 'Add payment', 'team-membership-ledger' ),
			isEligible: ( item ) => item.status !== 'paid' && item.orderCount <= 1,
			RenderModal: ( { items, closeModal } ) => (
				<AddPaymentModal
					item={ items[ 0 ] }
					productId={ productId }
					onRowUpdated={ onRowUpdated }
					onNotice={ onNotice }
					closeModal={ closeModal }
				/>
			),
		},
	];
}
```

- [ ] **Step 5: Wire actions, row-patching, and notices into `src/App.js`**

Add imports:

```js
import { Snackbar } from '@wordpress/components';
import { makeActions } from './actions';
```

Inside `App()`, add notice state and a row-update handler (after the existing `useState` calls):

```js
	const [ notice, setNotice ] = useState( null );

	const handleRowUpdated = ( updated ) => {
		setRows( ( current ) =>
			current.map( ( r ) => ( r.memberId === updated.memberId ? updated : r ) )
		);
	};

	const actions = useMemo(
		() => makeActions( { productId, onRowUpdated: handleRowUpdated, onNotice: setNotice } ),
		[ productId ]
	);
```

Pass `actions` to `<DataViews>` (add the prop):

```js
					actions={ actions }
```

Render the snackbar at the end of the returned markup, just before the closing `</div>`:

```js
			{ notice && (
				<div style={ { position: 'fixed', bottom: 20, left: 20, zIndex: 100000 } }>
					<Snackbar onRemove={ () => setNotice( null ) }>{ notice.message }</Snackbar>
				</div>
			) }
```

- [ ] **Step 6: Build**

Run: `cd plugins/team-membership-ledger && npm run build`
Expected: builds cleanly.

- [ ] **Step 7: End-to-end browser verification (the money moment)**

Open **Membership → Ledger**, select a product. On an "Owes" or "Not entered" row, open the row actions (⋮) → **Add payment**, enter an amount, submit. Expected: the modal closes, a "Payment recorded." snackbar appears, and the row's Paid/Balance/Status update in place without a page reload. Confirm the order in **WooCommerce → Orders** reflects the new `_tml_amount_paid` (and flips to Completed if fully paid). Verify a "Paid" row and a multi-order row show **no** Add-payment action.

- [ ] **Step 8: Commit**

```bash
cd plugins/team-membership-ledger
git add src/Rest/LedgerController.php src/api.js src/actions.js src/App.js
git commit -m "feat: inline add-payment via DataViews action and REST write"
```

---

## PHASE 2 — LEDGER COMPLETE

### Task 6: `mark-paid` write route + action

**Files:**
- Modify: `plugins/team-membership-ledger/src/Rest/LedgerController.php` (add route + callback)
- Modify: `plugins/team-membership-ledger/src/api.js` (add `markPaid`)
- Modify: `plugins/team-membership-ledger/src/actions.js` (add mark-paid action)

**Interfaces:**
- Consumes: `OrderRepository::markPaid()`, existing `resolve_order()`/`row_response()`.
- Produces: route `POST tml/v1/ledger/mark-paid` (`product_id` req, `order_id`|`member_id`) returning the updated row; JS `markPaid({ productId, orderId, memberId })`; a `mark-paid` action (direct callback, no modal).

- [ ] **Step 1: Register the route and callback**

In `LedgerController::register()`, after the add-payment route:

```php
		register_rest_route(
			self::NAMESPACE,
			'/ledger/mark-paid',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'mark_paid' ),
				'permission_callback' => array( self::class, 'can_manage' ),
				'args'                => array(
					'product_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'order_id'   => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
					'member_id'  => array( 'type' => 'integer', 'required' => false, 'sanitize_callback' => 'absint', 'default' => 0 ),
				),
			)
		);
```

Add the callback:

```php
	public static function mark_paid( \WP_REST_Request $request ) {
		$product_id = absint( $request['product_id'] );
		$order_id   = absint( $request['order_id'] );
		$member_id  = absint( $request['member_id'] );

		try {
			$order_id = self::resolve_order( $order_id, $member_id, $product_id );
			( new OrderRepository() )->markPaid( $order_id );
		} catch ( \RuntimeException $e ) {
			return new \WP_Error( 'tml_action_failed', $e->getMessage(), array( 'status' => 400 ) );
		}

		return self::row_response( $product_id, $member_id ?: null, $order_id );
	}
```

- [ ] **Step 2: Smoke-test**

```bash
studio wp eval '$r = new WP_REST_Request("POST","/tml/v1/ledger/mark-paid"); $r->set_param("product_id", <PRODUCT_ID>); $r->set_param("member_id", <MEMBER_ID>); echo wp_json_encode( \OrillaEagles\Ledger\Rest\LedgerController::mark_paid($r)->get_data() );'
```
Expected: the member's row returns with `status` = `paid`, `paid` = `total`, `balance` = 0.

- [ ] **Step 3: Add `markPaid` to `src/api.js`**

```js
/**
 * Mark an order fully paid; returns the updated row.
 *
 * @param {{productId:number, orderId:?number, memberId:?number}} args Payload.
 * @return {Promise<Object|null>} Updated serialized row, or null.
 */
export function markPaid( { productId, orderId, memberId } ) {
	return apiFetch( {
		path: '/tml/v1/ledger/mark-paid',
		method: 'POST',
		data: {
			product_id: productId,
			order_id: orderId || 0,
			member_id: memberId || 0,
		},
	} );
}
```

- [ ] **Step 4: Add the mark-paid action in `src/actions.js`**

Add `markPaid` to the import:

```js
import { addPayment, markPaid } from './api';
```

In `makeActions`, add a second action to the returned array (after `add-payment`):

```js
		{
			id: 'mark-paid',
			label: __( 'Mark paid', 'team-membership-ledger' ),
			isEligible: ( item ) => item.status !== 'paid' && item.orderCount <= 1,
			callback: async ( items ) => {
				const item = items[ 0 ];
				try {
					const updated = await markPaid( {
						productId,
						orderId: item.orderId,
						memberId: item.memberId,
					} );
					if ( updated ) {
						onRowUpdated( updated );
					}
					onNotice( { type: 'success', message: __( 'Marked paid.', 'team-membership-ledger' ) } );
				} catch ( e ) {
					onNotice( { type: 'error', message: e.message || __( 'Action failed.', 'team-membership-ledger' ) } );
				}
			},
		},
```

- [ ] **Step 5: Build**

Run: `cd plugins/team-membership-ledger && npm run build`
Expected: builds cleanly.

- [ ] **Step 6: Browser verification**

On an "Owes"/"Not entered" row, row actions → **Mark paid**. Expected: the row flips to Paid, Balance 0, snackbar "Marked paid.", both actions disappear from that row (now `paid`). No reload.

- [ ] **Step 7: Commit**

```bash
cd plugins/team-membership-ledger
git add src/Rest/LedgerController.php src/api.js src/actions.js
git commit -m "feat: mark-paid via DataViews action and REST write"
```

---

### Task 7: Retire the legacy form path

**Files:**
- Modify: `plugins/team-membership-ledger/src/Admin/LedgerScreen.php` (strip table, `actions()`, `label()`, `handlePost()`)
- Modify: `plugins/team-membership-ledger/src/Plugin.php` (remove the `LedgerScreen::handlePost` `admin_init` hook)

**Interfaces:**
- Produces: `LedgerScreen::render()` outputs only the heading + `#tml-ledger-root`; `LedgerScreen::enqueue()` unchanged.

- [ ] **Step 1: Remove the handlePost hook in `Plugin::boot()`**

In `src/Plugin.php::boot()`, delete this line:

```php
		add_action( 'admin_init', array( \OrillaEagles\Ledger\Admin\LedgerScreen::class, 'handlePost' ) );
```

(Leave the RosterScreen and RolloverScreen `handlePost` hooks intact.)

- [ ] **Step 2: Slim down `LedgerScreen.php`**

Replace the entire body of `src/Admin/LedgerScreen.php` with the reduced version (drops `label()`, `handlePost()`, `actions()`, and the table markup; keeps `enqueue()` and a minimal `render()`):

```php
<?php
namespace OrillaEagles\Ledger\Admin;

defined( 'ABSPATH' ) || exit;

final class LedgerScreen {

	public static function enqueue( string $hook ): void {
		if ( strpos( $hook, 'tml-ledger' ) === false ) {
			return;
		}
		$asset_file = TML_DIR . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'tml-ledger-app',
			plugins_url( 'build/index.js', TML_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'tml-ledger-app', 'team-membership-ledger' );
		// DataViews styles are bundled into build/index.js; host component styles
		// are still needed for Modal/Button/SelectControl chrome. There is no
		// wp-dataviews style handle on this site (see Global Constraints).
		wp_enqueue_style( 'wp-components' );

		wp_localize_script(
			'tml-ledger-app',
			'tmlLedger',
			array(
				'productId' => isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	public static function render(): void {
		if ( ! current_user_can( Menu::CAP ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Ledger', 'team-membership-ledger' ); ?></h1>
			<div id="tml-ledger-root"></div>
		</div>
		<?php
	}
}
```

- [ ] **Step 3: Run the PHP tests (no regressions)**

Run: `cd plugins/team-membership-ledger && composer test`
Expected: PASS (the domain/serializer suites are unaffected).

- [ ] **Step 4: Browser verification**

Open **Membership → Ledger**. Expected: only the DataViews UI renders (no legacy PHP table beneath it). Add-payment and mark-paid still work end-to-end (retest one of each). Confirm the old `?product_id=` GET form is gone and the product selector inside the app drives everything.

- [ ] **Step 5: Commit**

```bash
cd plugins/team-membership-ledger
git add src/Admin/LedgerScreen.php src/Plugin.php
git commit -m "refactor: remove legacy Ledger form path in favor of DataViews"
```

---

## Verification Summary

- **PHP unit tests:** `cd plugins/team-membership-ledger && composer test` (pure — `LedgerSerializer`, `PaymentCalculator`, `LedgerCalculator`, `RolloverPlan`).
- **JS unit test:** `cd plugins/team-membership-ledger && npm run test:js` (`formatMoney`).
- **Build:** `cd plugins/team-membership-ledger && npm run build`.
- **REST smoke tests:** `studio wp eval` snippets in Tasks 3, 5, 6.
- **End-to-end:** browser checks in Tasks 4, 5, 6, 7 (data renders; add-payment and mark-paid update rows in place; eligibility hides actions on paid/multi-order rows; legacy path gone).

## Out of Scope (this plan)

- **Roster rebuild (design Phase 3)** — separate follow-up plan reusing this build/REST plumbing.
- Season Rollover screen (unchanged).
- Editing payments down, refunds, or un-completing from the Ledger.
- Server-side pagination/filtering (dataset is small; DataViews handles it client-side).
