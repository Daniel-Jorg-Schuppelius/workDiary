---
title: "Lexoffice Conflicts"
topic: admin.lexoffice
version: 1
audience:
    - admin
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
