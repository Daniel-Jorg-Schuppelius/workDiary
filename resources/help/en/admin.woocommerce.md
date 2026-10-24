---
title: "WooCommerce order import"
topic: admin.woocommerce
version: 1
keywords:
    - WooCommerce
    - WordPress
    - shop
    - import orders
    - online shop
    - consumer key
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

The **WooCommerce** page reads the orders of your WooCommerce shop and shows
them in WorkDiary: number, date, buyer, status and amount. Buyers are assigned
to existing customers, so their orders also appear on the customer. WorkDiary
writes nothing to the shop. You find the page in the system menu (gear
**System**) under **Plugins** → **WooCommerce** once the plugin is active.

## Set up access

1. In WooCommerce, create a key with **Read** permission under **Settings** →
   **Advanced** → **REST API**.
2. On the page, open **Credentials** in the ⋯ menu and enter the **shop
   address** (with https, without /wp-json), the **consumer key** and the
   **consumer secret**. WooCommerce accepts the keys only over https. If the
   shop runs in your own network, also set **Allow private addresses** – this
   only takes effect if the operator permits it.
3. Click **Check connection**. WorkDiary reads one order as a test and shows
   the shop’s name.

As long as orders are mirrored, no other shop can be entered.

## Preview and confirm the import

**Check preview** shows the orders in the time window of the first sync
(default 30 days, adjustable under **Credentials**) and whether their buyer is
already assigned to a customer. The preview saves nothing. **Confirm import**
releases the sync.

New cancelled, failed or refunded orders are only taken over if **Take over
cancelled and refunded orders** is set. Orders already taken over are always
updated.

## What is taken over

- Order number, date, **status** from the fixed WooCommerce status (open,
  processing, completed, cancelled, refunded) and amount with currency.
- Refunds appear below the amount (“of which refunded”); the order total stays
  as in the shop.
- Once a day the sync checks whether the imported orders still exist in the
  shop. Deleted ones then show **Deleted in the shop** and remain in WorkDiary;
  if one reappears, the marker is removed.
- Buyer with name, e-mail and company, the line items as text.
- The order number leads to the order administration of the shop.

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

- The check reports `https_required`: the shop address does not start with https.
- The check fails with 401: the keys are wrong or lack read permission.
- The check reports an internal address: the shop address points into your own
  network, but **Allow private addresses** is off or not permitted by the operator.
- The check fails with 401 although the keys are correct: behind a proxy,
  WordPress does not recognize the https connection, or the server does not pass
  on the Authorization header.
- The check reports 404: **Settings** → **Permalinks** is set to “Plain”; the
  REST API needs pretty permalinks.
