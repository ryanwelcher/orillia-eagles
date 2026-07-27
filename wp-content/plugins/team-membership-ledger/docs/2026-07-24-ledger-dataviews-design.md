# Ledger DataViews Rebuild — Design

Date: 2026-07-24
Status: Approved

## Purpose

Rebuild the admin Ledger UI (and, in a later phase, the Roster) using the
WordPress **DataViews** component (`@wordpress/dataviews`). The current
`wp-list-table`-style screen renders via PHP and drives every action through a
full-page HTML form POST. The goals:

- Polish — the native, modern WP-admin look DataViews gives out of the box.
- Built-in sorting, filtering, search, and pagination.
- Switchable layouts (table / grid / list) and configurable visible fields.
- Consistency with the React-based WordPress admin for future-proofing.

The migration must preserve every existing behavior, especially the **inline
payment updating** (add partial payment, mark paid, on-the-fly order creation),
with the same security model.

## Reference material

- https://developer.wordpress.org/news/2024/08/using-data-views-to-display-and-interact-with-data-in-plugins/
- https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dataviews/

## Decisions (from brainstorming)

- **Edit model — row actions + modal.** DataViews' table view is not a
  spreadsheet; it has no click-into-cell editing. Payment actions become row
  actions: **Mark paid** fires directly; **Add payment** opens a small modal with
  the amount field. This is the idiomatic DataViews pattern — most native look,
  least custom code, cleanest fit with sorting/selection. The one UX change from
  today: Add payment gains a modal step instead of a raw in-row input.
- **Scope — Ledger + Roster, Ledger first.** The Ledger is the tracer bullet (it
  carries the inline money editing). Roster follows on the same plumbing. Season
  Rollover is unchanged.
- **Build tooling — add `@wordpress/scripts`.** None exists today. Standard
  `src/` → `build/` webpack with a single admin script entry (no block.json).
  **DataViews is bundled, not externalized** (spike-proven): this site's
  Gutenberg does not register a `wp-dataviews` handle, so a custom
  `webpack.config.js` bundles `@wordpress/dataviews@17.2.0` and externalizes the
  rest to host handles, with a `resolve.alias` fixing `@wordpress/icons@15.2.0`'s
  broken ESM `exports`. The DataViews stylesheet is imported in `src/index.js`
  and extracted to `build/style-index.css` (a webpack `sideEffects` rule keeps
  it from being tree-shaken away, since the package declares `sideEffects:false`);
  `enqueue()` ships and loads that file since this site has no `wp-dataviews`
  style handle.
- **Data delivery — REST read + client-side view state.** One REST read endpoint
  returns the shaped rows (reusing `LedgerCalculator` server-side). DataViews
  does sorting/filtering/pagination client-side — the active roster is dozens of
  rows, so server-side pagination is needless complexity. Writes hit REST write
  endpoints, then the client refetches. Single source of truth; no duplicated
  row-shaping logic.
- **Product selector stays a control above the view.** It defines *which* dataset
  loads (a `GET` param), not a DataViews filter.

## Architecture

```
Browser (React)                          Server (PHP, existing domain layer)
┌────────────────────────────┐          ┌─────────────────────────────────┐
│ Ledger admin page          │          │ REST: tml/v1                    │
│  ├ Product <select>        │  GET     │  GET  /ledger?product_id=…      │──▶ LedgerCalculator::forProduct()
│  └ <DataViews>             │◀────────▶│  POST /ledger/mark-paid         │──▶ OrderRepository::markPaid()
│      ├ fields (Member…Bal) │  POST    │  POST /ledger/add-payment       │──▶ OrderRepository::addPayment()
│      └ actions:            │          │         (amount = increment)    │    └ PaymentCalculator::apply()
│         • Mark paid        │          │                                 │
│         • Add payment→modal│          │ All gated: manage_woocommerce   │
└────────────────────────────┘          │ + REST nonce (X-WP-Nonce)       │
                                         └─────────────────────────────────┘
```

**Nothing in `src/Domain` or `src/Data` changes.** The REST controller is a thin
adapter over code that already exists and is unit-tested. The old
`LedgerScreen::handlePost()` form path is retired once REST works.

## Components

1. **`Rest/LedgerController`** (new). Registers `tml/v1` routes on `rest_api_init`:
   - `GET /ledger` — args: `product_id` (absint, required). Returns
     `{ rows: [...], product: {id, name}, products: [{id, name}] }`. Rows are the
     serialized `LedgerRow` objects from `LedgerCalculator::forProduct()`.
   - `POST /ledger/add-payment` — args: `product_id`, `amount` (>0), and either
     `order_id` or `member_id`. Lazily creates the Requested order when only
     `member_id` is given, then calls `OrderRepository::addPayment()`. Returns the
     updated row.
   - `POST /ledger/mark-paid` — args: `product_id`, and either `order_id` or
     `member_id`. Lazily creates the order when needed, then
     `OrderRepository::markPaid()`. Returns the updated row.
   - Every route: `permission_callback` requiring `manage_woocommerce`; standard
     REST nonce (`X-WP-Nonce`); `absint`/`floatval` sanitize + `amount > 0` guard,
     mirroring the current `handlePost()` rules exactly.
2. **`Admin/LedgerScreen`** (modified). `render()` becomes a mount point: a root
   `<div>` plus an `admin_enqueue_scripts` hook (Ledger page only) that enqueues
   the built asset with its generated dependency array and passes the REST
   root/nonce and initial `product_id` via `wp_add_inline_script` /
   `wp_localize_script`. The PHP table markup and `handlePost()` form path are
   removed once REST is proven.
3. **JS app** (`src/index.js` + modules → `build/`). Renders `<DataViews>`:
   - **fields:** Member (name + email), Status (elements: Paid/Owes/Not entered),
     Qty, Total, Paid, Balance, Date. Money formatted client-side.
   - **actions:** `mark-paid` (direct, with confirm) and `add-payment`
     (RenderModal with a numeric amount field). Both use `isEligible(item)` to
     hide on Paid rows and on multi-order rows; multi-order rows render the
     "Multiple orders — manage in WooCommerce" hint.
   - Product `<select>` above the view; changing it refetches `GET /ledger`.
   - After a successful write, refetch (or patch the single row) so status,
     paid, balance, and auto-complete state re-render.
4. **Build config.** `package.json` with `@wordpress/scripts`; `npm run build`
   emits `build/index.js` + `build/index.asset.php`.

## Behavior parity (must be preserved)

| Today (HTML form + reload)                 | DataViews version                                                        |
|--------------------------------------------|--------------------------------------------------------------------------|
| Add payment (additive increment)           | Row action → modal → `POST add-payment` → refetch                        |
| Mark paid (one click)                      | Row action, direct (optional confirm) → `POST mark-paid`                 |
| "Not entered" → create order on the fly    | Write routes accept `member_id`; controller calls `createRequestedOrder` |
| "Paid" rows show no controls               | Action `isEligible` returns false for paid rows                          |
| Multiple orders → read-only WooCommerce hint | `isEligible` false + rendered hint in the row                          |
| Auto-flip to Completed when fully paid     | Unchanged — in `addPayment`/`PaymentCalculator`; REST returns new state  |
| Cap + nonce + sanitize                     | `permission_callback` = `manage_woocommerce`, `X-WP-Nonce`, same guards  |

Additive-payment semantics are unchanged: the typed amount is "amount received
now" and is ADDED to the running `_tml_amount_paid`; the order auto-completes when
the total is reached. No editing amounts down, no refund/undo from the Ledger —
still out of scope, still done in WooCommerce → Orders.

## Tracer bullet (Phase 1)

A razor-thin slice through every layer to prove the architecture — especially the
inline write path — before building breadth:

1. Stand up build tooling (`@wordpress/scripts`, `package.json`, `src/index.js`,
   `build/`).
2. Enqueue the built asset on the Ledger page only, with REST root + nonce.
3. `GET tml/v1/ledger` read route returning shaped rows (reusing
   `LedgerCalculator`), gated by `manage_woocommerce`.
4. DataViews renders read-only with the real columns — proves data round-trips
   and looks native.
5. `POST tml/v1/ledger/add-payment` + the single Add-payment row action
   (modal → POST → refetch). **This proves inline updating works through
   DataViews.**

Deliberately skipped in the tracer bullet (fast-follows, not risks): Mark paid,
on-the-fly order creation, the multi-order guard, notice polish, Roster.

## Phasing

- **Phase 1 — Tracer bullet:** build + enqueue + read route + read-only DataViews
  + `add-payment` action.
- **Phase 2 — Ledger complete:** Mark paid, on-the-fly order creation, paid/
  multi-order eligibility rules, product selector, success/error notices, retire
  the old form path.
- **Phase 3 — Roster on the same plumbing:** DataViews list of members with the
  active/inactive toggle as a row action, reusing the REST + build setup.

## Testing

- **PHP:** unit tests for the REST controller's sanitize/permission logic and the
  member-id → lazy-order path. Domain layer (`PaymentCalculator`,
  `LedgerCalculator`) already covered — no changes there.
- **JS:** the build must load without console errors; the tracer bullet is
  verified by adding a real payment through the modal and confirming the row's
  paid/balance/status update and the order state in WooCommerce.

## Out of scope

- Season Rollover screen (unchanged).
- Editing payments downward, refunds, or un-completing from the Ledger.
- Server-side pagination / server-side filtering (dataset is small).
- Online/card payments — this remains an offline-collection record.
