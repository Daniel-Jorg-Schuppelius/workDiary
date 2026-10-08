---
title: "Lexoffice Conflicts"
topic: admin.lexoffice
version: 4
keywords:
    - Lexware Office
    - sync conflict
    - synchronisation conflict
    - data mismatch
    - resolve conflict
    - keep local values
    - accept remote values
    - data reconciliation
    - contact sync
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Here you resolve synchronization conflicts with Lexoffice. A conflict
arises when a local record (WorkDiary) and the corresponding Lexoffice
contact diverge in one or more fields and the **Conflict strategy** in
the Lexoffice settings is set to **Manual review** (the default). The
conflicts are handled in the **Mapping Inbox**: opening this page takes
you there, filtered to the source **Lexoffice** and the case **Field
conflict**.

In the Mapping Inbox:

- For each conflict the differing fields are shown side by side as
  **Local** and **Remote**.
- Customers and suppliers are affected, i.e. the contacts from
  Lexoffice.
- The status filter also brings back conflicts that have already been
  resolved.

Resolution paths per conflict:

- **Adopt remote**: updates the local record with the Lexoffice values
  of the differing fields.
- **Keep local**: retains the local values; the differing Lexoffice
  values are not applied.
- **Dismiss**: closes the conflict without any change (e.g. for
  intentionally different data); it receives the status **Discarded**.

Risks: **Adopt remote** overwrites local values. Review the compared
data carefully before deciding. Note that for invoices the billing
authority rests with the external program – WorkDiary supplies data to
it.

The conflict strategy applies to contacts and articles. Article
conflicts do not appear in the Mapping Inbox but in the **Conflicts**
tab of the inventory (**Inventory** → **Conflicts**): there you choose
**Keep local**, **Take Lexoffice state** or **Dismiss** — with the
**Manage articles** permission.

Permission: the Mapping Inbox is open to administrators and the
**Accounting** role.
