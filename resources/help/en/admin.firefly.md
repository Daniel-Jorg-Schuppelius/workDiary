---
title: "Firefly III as a reconciliation source"
topic: admin.firefly
version: 1
keywords:
    - Firefly III
    - account movements
    - incoming payment
    - reconciliation
    - description
    - personal access token
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
    - admin.scheduler
---

The **Firefly III** page reads the deposits and withdrawals of your Firefly III
installation and shows which invoice or incoming invoice a movement belongs to.
You find the page in the system menu (gear **System**) under **Plugins** →
**Firefly III** once the plugin is active. WorkDiary writes nothing to Firefly III
and books no payments from the movements.

## Set up access

1. In Firefly III, create a **personal access token** under **Options** →
   **Profile** → **OAuth**.
2. On the page, open **Credentials** in the ⋯ menu and enter the **address**
   (without /api) and the token. If Firefly III runs in your own network, also set
   **Allow private addresses** – this only takes effect if the operator permits it.
3. Click **Check connection**. WorkDiary shows the instance, version and account.

## What is reconciled

Each run reads the movements in the **time window** (default 60 days up to today,
adjustable under **Credentials**). Transfers between your own accounts are left
out.

- **Deposit:** if the description, notes, reference or SEPA end-to-end ID contain exactly one invoice
  number of your organization, the row shows that invoice.
- **Withdrawal:** the same with the number of an incoming invoice.
- If a transaction has an amount in a second currency, it is shown below the
  amount.
- A split transaction appears as one row per split.
- If several documents match, the movement is **Ambiguous**; without a hit it is
  **Not assigned**. Amount and date alone never assign.

Whatever is missing in Firefly III within a completely read time window disappears
from the list. An aborted run counts as **incomplete** and removes nothing.

## When syncing happens

Hourly; change the schedule under **Scheduled jobs**, **Sync now** starts
immediately. **Disconnect** ends the sync.

## Common errors

- The check fails with 401: the token is wrong or expired.
- The check reports an internal address: the address points into your own
  network, but **Allow private addresses** is off or not permitted by the operator.
