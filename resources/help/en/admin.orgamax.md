---
title: "orgaMAX accounting"
topic: admin.orgamax
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

orgaMAX accounting is connected as a per-organisation plugin via the
official OpenAPI (not orgaMAX ERP). orgaMAX remains the leading system for
activated capabilities.

Connection:

1. **Start a connection intent** (private pilot mode with API key/secret or
   published extension with operator secret). WorkDiary generates a callback
   URL with a state token.
2. Store the URL as the extension URL in orgaMAX and open it — orgaMAX
   appends the `iid`. A foreign `iid` without a valid intent is never bound.
3. **Explicitly confirm** the detected account; the scope preflight blocks
   on missing grants instead of partially activating.

Data ownership per capability (customers, suppliers, articles, billing,
payments, expenses, documents): exactly one system leads; the safe default
is manual review. Master data is matched via the integration inbox — no
shadow master data.

Billing: released transfers (Finance → Transfers, target orgaMAX) create at
most ONE orgaMAX order (source marker + reconciliation instead of blind
retries). Converting to an invoice, irreversible locking, sending and
recording payments are separate, individually permissioned and audited
actions. Invoice number, status, payment and PDF visibly come from orgaMAX.

Polling runs budgeted with checkpoints (hourly, configurable); "Sync now"
respects the same limits. Expense/receipt hand-over stays blocked until the
receipt pilot is confirmed.
