# Team Membership Ledger — Design

Date: 2026-07-23
Status: Approved

## Purpose

An internal WooCommerce back-office for a volunteer-run sports team (Orillia
Eagles) to track membership dues and event charges (banquets, game tickets) —
including money that is collected **offline**. The site records *who owes* and
*who has paid* for each item, per season. It is a management tool, not a public
storefront.

## Key decisions (from brainstorming)

- **Dues model:** manual / flexible (installments allowed). No auto-recurring
  billing, so no paid subscription extension needed.
- **Membership = payment tracking only.** No gated content, no member logins.
- **Events fulfilled offline.** The site tracks requests + paid status even when
  money does not flow through the website.
- **Coordinators enter everything.** Members never log in; a few admins create
  orders on members' behalf.
- **Scope:** membership/payments management only. No public website.
- **Roster source of truth:** an explicit roster of member customers,
  independent of orders, so the ledger can show members who have not paid — or
  not been entered — yet.

## Stack

- WordPress + WooCommerce (core, free). **WooCommerce is a hard dependency.**
- Free helpers: *Advanced Order Export for WooCommerce* (treasurer CSVs); a
  custom order status "Requested" (added by our plugin, not a separate plugin).
- One small custom plugin: `team-membership-ledger` (this project).
- No Jetpack required. No storefront/theme work — everything lives in wp-admin.

## Data model

- **Members = WooCommerce customers**, maintained as an *explicit roster* with
  an **Active / Inactive** flag (plus name, email, optional team/notes). The
  roster is the source of truth for "who is a member."
- **Products**, grouped by **product category**:
  - *Dues* → one product per season ("2026 Membership Dues", "2027 Membership
    Dues", …).
  - *Banquets* → e.g. "2026 Banquet".
  - *Game Tickets* → per game/event as needed.
  - Product-per-season keeps reports and the ledger dropdown clean; "2026
    membership" is simply that product.
- **Orders = the ledger.** A coordinator creates an order on a member's behalf
  via WooCommerce's Add Order screen (or via the Season Rollover button).
  - Offline payment methods enabled and renamed to reality: **Cash, e-Transfer,
    Cheque** (built on WooCommerce's BACS/Cheque/COD gateways).
  - **Order status = paid flag:** *Requested* (custom) / Pending / On-hold =
    owes; *Completed* = paid.
- **Seasons** handled by product-per-season (no custom taxonomy).

## Installments (the one rough edge)

Native WooCommerce cannot show "paid $50 of $200" on one order. Chosen pattern:
- Keep the order at *On-hold* until fully paid; record each payment as an order
  note **and** maintain a single `amount_paid` meta the ledger reads to show a
  running balance. Only flip to *Completed* when fully paid.
- (Rejected alternative: one order per installment — cleaner money math, messier
  roster.)

## Custom plugin — screens

The plugin adds a top-level wp-admin menu. It reads WooCommerce order data and
creates standard WooCommerce orders; it never reimplements checkout.

1. **Roster** — list member customers with Active/Inactive toggle and basic
   fields. Add/edit members (thin wrapper over WooCommerce customers / WP users
   with the customer role). "Active" is the flag Season Rollover targets.

2. **Per-product Ledger** (primary view) — a product dropdown; selecting e.g.
   "2026 Membership Dues" renders a table of **every active member as a row**
   with status **Paid / Owes / amount / qty / date**, including members with no
   order yet (shown as "Not entered"). A toggle switches to a per-member view
   (pick a member → all their items + statuses). Installment balances show here.
   Built by rolling up WooCommerce order line items by customer, so every
   product appears automatically.

3. **Season Rollover** (bulk generator) — enter/select a product (create inline
   if needed), press **Generate**. Creates one *Requested* (unpaid) order per
   **active** member for that product, **skipping** anyone who already has one
   (safe to re-run). Shows a summary ("58 created, 2 skipped"). Coordinators
   then work the Ledger, flipping each to Paid as money arrives.

## Reporting & exports

- Day-to-day "who owes" lives in the Ledger screen.
- Advanced Order Export (free) gives the treasurer product+status CSVs.
- WooCommerce native Analytics still provides revenue/totals by product.

## Out of scope (deliberate, addable later without rework)

Member logins/self-service; online card payments; real ticketing / QR check-in;
public website; automated payment reminders; Jetpack.

## Build order

1. WooCommerce install + offline gateways + custom "Requested" order status.
2. Products & categories.
3. Roster / member customers.
4. Custom plugin: Roster screen → Ledger screen → Rollover button.
5. Export plugin + treasurer workflow.
