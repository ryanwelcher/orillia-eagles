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
   "2027 Membership Dues". Tick **Charge per player** (Product data → General)
   on products billed per player, such as dues. Leave it off for products billed
   per member, such as banquet tickets.
   **Quantity:** in the Ledger you can edit the quantity (Qty) of charges for
   products where members may order more than one, such as event tickets. Tick
   **Sold individually** (Product data → Inventory) on products that must stay
   at 1. "Charge per player" products are always 1. A new quantity uses the
   charge's original unit price, and the order becomes Paid or Owes based on
   what has been paid so far.
5. **Treasurer exports** (optional, free) — install *Advanced Order Export for
   WooCommerce* to export who-paid-what CSVs filtered by product + status.

## Daily use

- **Membership → Roster** — add members (WooCommerce customers) and mark them
  Active/Inactive. Only Active members are charged by Season Rollover. In the
  **Players** column, link each player (from the Players plugin) to the member
  who pays for them. Each player has one paying member.
- **Membership → Ledger** — pick a product to see each active member's status
  (Paid / Owes), and record payments inline. Members with no charge yet
  (*Not entered*) are hidden. Turn on **Show members without charges** to list
  them, so you can create a charge from the row:
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

## Per-player products

- For a **Charge per player** product, Season Rollover creates one order for
  each player linked to an active member. The order is in the member's name, and
  the player is saved in the order meta `_tml_player_id`. Members with no linked
  players are not charged for that product. Only roster entries tagged **Player**
  (or not tagged with a roster role yet) are billed — coaches, and any roster
  role added later, are skipped.
- In the Ledger, each member row shows the full amount for all of their players
  (total, paid, balance). Status is *Paid* only when every player is paid. Use
  the **Show linked players** toggle to show or hide the player rows under each
  member. Each player row shows its own status and balance.
- Record payments on the **member row**, not the player rows. **Add payment**
  pays off one player at a time, in alphabetical order, and cannot be more than
  the member owes. **Mark paid** marks every player paid. Both first create the
  charge for any linked player who doesn't have one yet.
- If you turn on **Charge per player** after member-level orders already exist
  (or turn it off after player orders exist), the Ledger does not show those
  older orders. Manage them in WooCommerce. A member who has one is not charged
  again for that product: Season Rollover skips them, and the Ledger refuses to
  create a new charge for them.
- To move a player to a different member, unlink them and then link them again.
  A player with an unpaid charge cannot be unlinked, because the charge and what
  was paid toward it would drop out of the Ledger. Settle or cancel that order
  first. A paid charge stays with the member who paid it, and the player is not
  charged again for that product under the new member.

## Recording payments (details)

- An order's **status is the paid flag**: *Requested* = owes, *Completed* = paid.
- The Ledger's **Add payment** stores the running total in the order meta
  `_tml_amount_paid` and auto-completes the order when it reaches the total.
- To reverse a payment or mark an order back to unpaid, edit the order in
  **WooCommerce → Orders**.
- If two people act on the same member at the same time, the second one is
  refused rather than charging twice or losing a payment. The same applies to a
  Ledger screen that is out of date: it will not create a charge that already
  exists. Reload the Ledger and try again.
- An order that is Cancelled, Refunded or Failed in WooCommerce still shows as a
  row, but the Ledger will not take a payment on it or change its quantity.
- A charge's amount is its order total, so tax or a fee added to the order is
  part of what the member owes and what a payment is checked against.

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
