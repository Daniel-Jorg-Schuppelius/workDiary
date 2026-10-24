---
title: "Shopware order import"
topic: admin.shopware
version: 1
keywords:
    - Shopware
    - Shopware 6
    - shop
    - import orders
    - online shop
    - integration
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

The **Shopware** page reads the orders of your Shopware 6 shop and shows
them in WorkDiary: number, date, buyer, status and amount. Buyers are assigned
to existing customers, so their orders also appear on the customer. WorkDiary
writes nothing to the shop. You find the page in the system menu (gear
**System**) under **Plugins** → **Shopware** once the plugin is active.

## Set up access

1. In Shopware, create an integration under **Settings** → **System** →
   **Integrations**. It needs read
   permission on orders, order customers, customers, line items, payment
   transactions, currencies and states – or the **Administrator** option.
2. On the page, open **Credentials** in the ⋯ menu and enter the **shop
   address** (without /api), the **access key ID** and the **secret access key**
   of the integration. If the shop runs in your own network, also set **Allow
   private addresses** – this only takes effect if the operator permits it.
3. Click **Check connection**. WorkDiary signs in, reads one order as a test and
   shows the address and the Shopware version.

As long as orders are mirrored, no other shop can be entered.

## Preview and confirm the import

**Check preview** shows the orders in the time window of the first sync
(default 30 days, adjustable under **Credentials**) and whether their buyer is
already assigned to a customer. The preview saves nothing. **Confirm import**
releases the sync.

New cancelled orders are only taken over if **Take over
cancelled and refunded orders** is set. Orders already taken over are always
updated.

## What is taken over

- Order number, date, **status** from the technical order state of Shopware
  (open, processing, completed, cancelled) and amount with currency. Shopware records refunds in the payment state: if the latest
  payment is refunded, the order counts as **refunded**; a partial refund does
  not change the status.
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

- The check fails with 401: the access key ID or secret access key is wrong.
- The check fails with 403: the integration lacks read permission on orders.
- The check reports an internal address: the shop address points into your own
  network, but **Allow private addresses** is off or not permitted by the operator.
- The check or a run fails with 403 although orders are readable: the
  integration lacks one of the read permissions named above, for example on
  payment transactions.
