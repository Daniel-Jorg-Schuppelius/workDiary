---
title: "Connect appointment booking (Calendly)"
topic: admin.calendly
version: 1
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
