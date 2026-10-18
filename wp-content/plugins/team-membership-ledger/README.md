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
  (Paid / Owes / Not entered), and record payments inline:
  - **Mark Paid** — marks that member's order Completed (paid in full). On a
    "Not entered" member it creates the charge first, then marks it paid.
  - **Add payment** — type the amount just received and click Add; it accrues
    toward the total (e.g. $50 then $30 on a $200 charge → "$80 of $200"). When
    the running total reaches the order total the order auto-completes. On a
    "Not entered" member it creates the charge first, then records the payment.
  - A member with multiple orders for one product shows a note to manage it in
    WooCommerce (inline actions are hidden to avoid targeting the wrong order).
- **Membership → Season Rollover** — pick a product and click Generate to raise
  a "Requested" (unpaid) order for every active member who doesn't already have
  one. Safe to re-run.

## Recording payments (details)

- An order's **status is the paid flag**: *Requested* = owes, *Completed* = paid.
- The Ledger's **Add payment** stores the running total in the order meta
  `_tml_amount_paid` and auto-completes the order when it reaches the total.
- To reverse a payment or mark an order back to unpaid, edit the order in
  **WooCommerce → Orders**.

## Local development note (SQLite)

If you run this plugin on a local WordPress Studio site backed by SQLite
(rather than MySQL), WordPress may log a harmless database error of the form
`SQLSTATE[HY000]: General error: 20 datatype mismatch` when the Ledger or
Season Rollover screens load.

The cause is WooCommerce core's own refund-cache priming, which internally
runs an order query with `limit => -1`; under HPOS that becomes an unsigned
64-bit LIMIT value that MySQL accepts as "no limit" but SQLite cannot
represent. It is triggered by WooCommerce core, not by this plugin.

It is harmless: the screens still show correct data, and it does not occur on
a MySQL-backed site (the normal production setup). No action is required.
