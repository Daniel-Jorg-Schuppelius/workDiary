---
title: "Lexoffice Conflicts"
topic: admin.lexoffice
version: 2
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
---

Here you resolve synchronization conflicts with Lexoffice. A
conflict arises when a local record (WorkDiary) and the
corresponding record in Lexoffice diverge in one or more fields and
synchronization requires a manual review.

Inbox:

- List of open conflicts with the differing fields plus snapshots of
  the local and the remote (Lexoffice) data.
- Contacts/customers, articles, vouchers and invoices can be
  affected.

Resolution paths per conflict:

- **Keep local**: retains the local values; the differing Lexoffice
  values are discarded.
- **Take remote**: updates the local record with the Lexoffice
  values of the differing fields.
- **Dismiss**: ignores the conflict (e.g. for intentionally
  different data); it is marked as done.

Risks: "keep local" and "take remote" overwrite values. Review the
compared data carefully before deciding. Note that for invoices the
billing authority rests with the external program – WorkDiary
supplies data to it.

The conflict strategy of the Lexoffice settings applies to contacts and
articles. Article conflicts do not appear in this inbox but in the inventory
conflict list (Inventory → Conflicts): there you keep the local state, take
the Lexoffice state or dismiss the conflict — with the article management
permission.
