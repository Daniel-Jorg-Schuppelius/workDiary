---
title: "Connect appointment booking (Calendly)"
topic: admin.calendly
version: 2
keywords:
    - online booking
    - booking page
    - appointment scheduling
    - scheduling tool
    - customer appointments
    - booked meetings
    - appointment inbox
    - cancelled appointments
    - rescheduling
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - appointments.inbox
---

This integration brings appointments that customers book on a scheduling page
into your appointment management.

**Connecting:** The connection is established once per organisation and then
applies to all booking pages of the connected account. Credentials are stored
encrypted and are no longer shown in clear text after saving.

**Incoming bookings:** New appointments first land in the **appointment
inbox**, not directly in the calendar. There they are matched to a customer —
unambiguous matches automatically, unclear cases wait for your decision. Only
then is an appointment created.

**Cancellations and reschedules** are followed up if the booking page reports
them. An appointment already adopted is not silently deleted but marked as
cancelled.

**Limits:** The integration reads bookings; it does not create booking pages
and does not change availability. You continue to maintain availability where
the booking page is managed.

**Without a customer match:** A booking whose address cannot be matched
unambiguously to a customer appears in the **integration inbox** as a case
targeting "Customer": assign (suggestions by name), create or discard.
Alternatively, assign the request to a customer or lead in the appointment
inbox.

**Mapping hosts:** Calendly hosts are recognised by their email address. If the
Calendly address differs, map it to a user under **Map Calendly users**; the
appointments then go to that user.

**Permissions:** Connecting, webhook, synchronisation, host mapping and booking
links are up to the organisation administration. Confirming and declining
appointment requests is allowed for whoever runs dispatch — on this page as
in the appointment inbox.

**Data ownership:** Bookings and cancellations from Calendly belong to the calendar area. If another system leads the calendar, WorkDiary neither creates nor changes an appointment request; the case waits in the integration inbox to be taken over once.
