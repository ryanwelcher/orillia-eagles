# Ledger Inline Payments — Design

Date: 2026-07-24
Status: Approved

## Purpose

Make the Ledger screen actionable: coordinators record payments directly from
the per-product roster instead of hopping to WooCommerce → Orders. Supports full
payment ("Mark Paid") and incremental partial payments, including creating the
charge on the fly for members not yet entered.

## Decisions (from brainstorming)

- **Not-entered rows:** payment controls create the Requested order on the fly
  (seeded from the product price via existing `createRequestedOrder`), then apply
  the action. No leaving the Ledger.
- **Partial input = "amount received now":** the typed amount is ADDED to the
  running total (`_tml_amount_paid`). When the running total reaches the order
  total, the order auto-flips to Completed; otherwise it stays Owes with a
  reduced balance.
- **Mark Paid = full Completed:** sets status Completed and amount-paid to the
  full order total.
- **Multi-order edge (rare):** if one member has 2+ orders for the same product,
  the row shows combined status but hides inline buttons with a "multiple orders
  — manage in WooCommerce" note, so a click can't target the wrong order. Normal
  workflow keeps this at one order per member+product.

## Behavior per row (active member, selected product)

- **Owes** (one order): `Mark Paid` button + `Add payment: $[amount] [Add]`.
- **Not entered** (no order): same controls; either action first creates the
  Requested order, then applies.
- **Paid**: no controls.
- **Multiple orders**: combined status, no inline controls, note to use
  WooCommerce.

## Components (fits existing architecture)

1. **`Domain/PaymentCalculator`** (pure, unit-tested): `apply(currentPaid,
   increment, orderTotal): {new_paid, should_complete}`. `new_paid =
   round(currentPaid + increment, 2)`; `should_complete = new_paid >=
   round(orderTotal, 2)`. Keeps money math WordPress-free and testable.
2. **Records carry `order_id`:** `OrderRepository::productRecords()` adds
   `order_id` to each record; `LedgerCalculator` collects distinct order ids per
   member into the row. `LedgerRow` gains `orderIds()`, `orderCount()`,
   `singleOrderId()`.
3. **`OrderRepository` write methods:**
   - `markPaid(int $orderId)`: set `_tml_amount_paid` = order total, status
     Completed.
   - `addPayment(int $orderId, float $increment)`: read current
     `_tml_amount_paid`, run `PaymentCalculator::apply` against the order total,
     store `new_paid`, and set status Completed when `should_complete`.
   - On-the-fly creation reuses `createRequestedOrder()`.
4. **`LedgerScreen` becomes read + write:** per-row forms; a `handlePost()`
   registered on `admin_init` with the same security as the other screens —
   `manage_woocommerce` capability + nonce (`tml_ledger`), sanitized inputs
   (absint ids, float amount > 0), PRG redirect back to the SAME product view
   (`product_id` preserved).

## Security

Capability `manage_woocommerce` on `handlePost` and `render`. Nonce
`tml_ledger` on every action form (`check_admin_referer`). Inputs unslashed +
sanitized (`absint` for order/member/product ids, `floatval` + `> 0` guard for
amount). All output escaped. Redirect-after-POST.

## Out of scope (YAGNI)

Refund/undo from the Ledger (use WooCommerce), editing arbitrary amounts down,
per-attendee detail, receipts. Marking an order back to unpaid is done in
WooCommerce.
