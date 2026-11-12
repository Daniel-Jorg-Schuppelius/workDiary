---
title: "Edit a work order"
topic: diary-entries.edit
version: 5
keywords:
    - change order
    - order details
    - case file
    - change status
    - complete job
    - close order
    - add attachment
    - add comment
    - mandatory classification
    - attachment size limit
audience: []
related:
    - diary-entries.create
    - protocols.create
---

Tap the order title in the order book list to open the detail view
(case file). Direct editing happens in the individual sections
(comments, attachments, times, protocols).

Keep in mind:

- Mandatory classifications must be filled in before the status can be
  changed to **Done**.
- Communication notes are kept chronologically below the order and stay
  visible for its entire lifetime.
- Attachments may be up to **25 MB**; larger files must be referenced
  via external storage.
- You change the **Planned duration (HH:MM)** in the edit dialog in the
  **Period** section. If you clear the field, the service duration of a dispatched order, otherwise the length of the time slot or
  the duration of the appointment applies again.

**Lifecycle:** You hand back an accepted order via **Return acceptance** with a
reason; the order is then "Planned" again. **Pause** and **Cancel** stop running
stopwatches on the order and book the time. **Complete** requires that no
stopwatch is running – the message names who is still active. A cancellation
archives open protocols and aborts running procedure runs; signed protocols
stay. On acceptance the person who signed at the customer is recorded; if the
protocol carries no customer signature, the dialog asks for the name.

**Invoiced:** When you issue an invoice that bills times of a signed-off order, or
hand these times over to your accounting program, the order switches to "Invoiced"
by itself and names the document. If the document is cancelled, the order is
"Signed off" again. Manual entry remains possible.

**Material:** A material invoice or the handover of material likewise marks the
order as invoiced if the material is on a timesheet with times of this order.

**Filtering the history:** Above the history you choose a period, one or more
types, a person and "Customer-relevant only".
