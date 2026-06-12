---
title: "Notification rules"
topic: admin.notification-rules
version: 1
audience:
    - admin
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Notification rules define per event type **who** is informed on
**which channels** – and when escalation kicks in.

Typical workflow:

1. Open an event in the list (e.g. open issue assigned/due
   soon/overdue, follow-up action due, document expiring, time
   correction request, month closure submitted, ISMS certificate
   expiring, corrective action overdue, risk review due).
2. Choose **channels**: "In-app", "E-mail", "Push".
3. Define **recipients**: affected person yes/no, recipient roles
   (e.g. team lead) and fixed additional recipients.
4. For overdue events optionally configure **escalation**: after
   1–720 hours the escalation role is notified in addition.

Good to know:

- Without an organization rule, the event's **code defaults** apply
  (channels, affected-person flag, roles) – you only need to
  configure deviating cases.
- Escalation exists only for overdue/expiry events.
- Some events fire immediately (e.g. assignment), others are found by
  the deadline scanner (e.g. "due soon").

Prerequisites: editing is reserved for admins of the organization.
