# Team Membership Ledger

Internal WooCommerce back-office for the Orillia Eagles: track who owes / who
has paid for per-season dues and events (banquets, game tickets), including
money collected offline. No public storefront.

Each **family** is a WooCommerce customer that pays for one or more
**players** (the `player` posts from the Orillia Players plugin). A self-paying
adult is a family of one. Families get a login, but it isn't used yet and no
welcome email is sent.

## Requirements & setup

1. **WooCommerce** installed and active (this plugin no-ops without it), plus
   the **Orillia Players** plugin for the `player` post type.
2. **HPOS** (WooCommerce → Settings → Advanced → Features → High-performance
   order storage) is supported; leave it on.
3. **Offline payment methods** — WooCommerce → Settings → Payments. Enable and
   rename the built-in offline gateways to your reality:
   - *Cash on delivery* → "Cash"
   - *Direct bank transfer (BACS)* → "e-Transfer"
   - *Cheque* → "Cheque"
4. **Products** — create one product per charge, grouped by category
   (Dues / Banquets / Game Tickets). Use one product per season, e.g.
   "Team Fees 2027". Its price is the fee per player.
5. **Treasurer exports** (optional, free) — install *Advanced Order Export for
   WooCommerce* to export who-paid-what CSVs filtered by product + status.

## Daily use

- **Membership → Families** — add families (WooCommerce customers) and mark
  them Active/Inactive. Click **Edit players** to tick the players a family
  pays for. Each player has exactly one family; ticking a player who belongs to
  another family moves them.
- **Membership → Create Charges** — pick a product to raise one "Requested"
  (unpaid) order per player whose family is Active, billed to that family.
  Players who already have one are skipped, so it's safe to re-run.
  Deactivating a family stops new charges; its existing charges stay on the
  Ledger.
- **Membership → Ledger** — pick a product to see one row per player, grouped
  by family. Each group header shows the player count and amount owing. Record
  payments inline:
  - **Mark paid** — marks that player's order Completed (paid in full). On a
    "Not entered" player it creates the charge first, then marks it paid.
  - **Add payment** — type the amount just received; it accrues toward the
    total (e.g. $50 then $30 on a $200 charge → "$80 of $200"). When the running
    total reaches the order total the order auto-completes. On a "Not entered"
    player it creates the charge first, then records the payment.
  - Players with no family are listed last under **Unassigned** (coaches
    excluded) so you can spot who still needs linking. They can't be charged
    until they have a family.
  - A player with multiple orders for one product shows a note to manage it in
    WooCommerce (inline actions are hidden to avoid targeting the wrong order).

## Recording payments (details)

- Each order is for **one player**: the order's customer is the family billed,
  and the order meta `_tml_player_id` is the player. A player's family is the
  player post meta `_tml_family_id`. If a player moves families, existing
  orders stay with the family they were billed to.
- An order's **status is the paid flag**: *Requested* = owes, *Completed* = paid.
- The Ledger's **Add payment** stores the running total in the order meta
  `_tml_amount_paid` and auto-completes the order when it reaches the total.
- To reverse a payment or mark an order back to unpaid, edit the order in
  **WooCommerce → Orders**.

## Local development note (SQLite)

If you run this plugin on a local WordPress Studio site backed by SQLite
(rather than MySQL), WordPress may log a harmless database error of the form
`SQLSTATE[HY000]: General error: 20 datatype mismatch` when the Ledger or
Create Charges screens load.

The cause is WooCommerce core's own refund-cache priming, which internally
runs an order query with `limit => -1`; under HPOS that becomes an unsigned
64-bit LIMIT value that MySQL accepts as "no limit" but SQLite cannot
represent. It is triggered by WooCommerce core, not by this plugin.

It is harmless: the screens still show correct data, and it does not occur on
a MySQL-backed site (the normal production setup). No action is required.
