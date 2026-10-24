---
title: "PrestaShop order import"
topic: admin.prestashop
version: 1
keywords:
    - PrestaShop
    - shop
    - import orders
    - online shop
    - webservice
    - assign buyers
    - open case
audience:
    - admin
related:
    - admin.plugins
    - admin.integration-inbox
    - admin.billbee
    - privacy.overview
    - admin.scheduler
---

The **PrestaShop** page reads the orders of your PrestaShop shop and shows
them in WorkDiary: number, date, buyer, status and amount. Buyers are assigned
to existing customers, so their orders also appear on the customer. WorkDiary
writes nothing to the shop. You find the page in the system menu (gear
**System**) under **Plugins** → **PrestaShop** once the plugin is active.

## Set up access

1. In PrestaShop, enable the webservice under **Advanced Parameters** →
   **Webservice** and create a key. It needs read permission (GET) on orders,
   customers, currencies and order_states, for refunds also on order_slip and for
   the time zone on configurations.
2. On the page, open **Credentials** in the ⋯ menu and enter the **shop
   address** (without /api) and the **webservice key**. PrestaShop delivers times
   without a time zone: if the shop does not run in your organization’s time
   zone, enter the **shop time zone** (e.g. Europe/Vienna). If the shop runs in
   your own network, also set **Allow private addresses** – this only takes
   effect if the operator permits it.
3. Click **Check connection**. WorkDiary reads one order as a test and shows the
   address.

As long as orders are mirrored, no other shop can be entered.

## Preview and confirm the import

**Check preview** shows the orders in the time window of the first sync
(default 30 days, adjustable under **Credentials**) and whether their buyer is
already assigned to a customer. The preview saves nothing. **Confirm import**
releases the sync.

New cancelled or refunded orders are only taken over if **Take over
cancelled and refunded orders** is set. Orders already taken over are always
updated.

## What is taken over

- Reference, date, **status** and amount with currency. The status follows the
  fixed properties of the PrestaShop order state, not its name: cancelled and
  payment error → cancelled, refund → refunded, shipped (also delivered) → completed, paid → processing, otherwise open.
- The delivery slip alone (default status “Processing in progress”) does not
  count as shipped. With several shops, WorkDiary reads the orders of all shops
  and looks up the currencies of other shops one by one.
- Refunds come from the credit slips (order_slip) and appear below the amount.
  If the key may not read order_slip, the refunded amount stays empty.
- If the key may read configurations, **Check connection** takes over the shop’s
  time zone; an entered **Shop time zone** takes precedence.
- Once a day the sync checks whether the imported orders still exist in the
  shop. Deleted ones then show **Deleted in the shop** and remain in WorkDiary;
  if one reappears, the marker is removed.
- Buyer with name, e-mail and company, the line items as text.
- There is no link into the shop administration: its folder is secret per
  shop, and its links only work with a sign-in.

## Assign customers and open cases

If the buyer is already assigned to a customer or clearly recognisable,
WorkDiary links the order to that customer. Otherwise an **open case** is
created in the integration inbox: assign the buyer to a customer there, and the
next sync links the buyer’s other orders as well. The import never creates new
customers. Guest orders are recognised by their e-mail address.

## When syncing happens

Every 15 minutes WorkDiary fetches the orders that are new or changed since the
last run, and the whole time window once a day. Change the schedule under
**Scheduled jobs**; **Sync now** starts immediately. An aborted run counts as
**incomplete**; the next one continues from the last complete state.

## Disconnect and retention

**Disconnect** ends the sync; orders and customer assignments remain. The
retention rule **Buyer data of marketplace orders** removes the buyers’ name,
e-mail and company after the commercial retention period; amounts and the
customer link remain.

## Common errors

- The check fails with 401: the key is wrong or the webservice is switched off.
- The check fails with 403 or a run stays **incomplete**: the key lacks read
  permission on one of the four resources.
- Times are off by hours: the **shop time zone** is missing or wrong.
- The check fails with 401 although the key is correct: the web server does not
  pass on the Authorization header (Apache: CGIPassAuth), or URL rewriting for
  /api is off.
