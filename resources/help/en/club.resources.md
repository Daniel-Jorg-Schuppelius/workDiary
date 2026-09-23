---
title: "Sports facilities and resources"
topic: club.resources
version: 1
audience: []
modules:
    - module.club
related:
    - club.events
    - club.matches
---

Sports facilities and resources model halls, partial areas (half, third),
tables, courts, lanes and stands, boats and equipment as a **tree**: a partial
area hangs under its hall, a table under the hall or a half. Conflict checking
is shared — booking the whole hall blocks all partial areas and tables below
it, different free partial areas can be used in parallel. There are no
isolated calendars per sport.

**Rooms and assets:** A resource can be linked to an existing room; then
events with that room and bookings of the resource (including partial areas)
share the same calendar. Boats and equipment can be linked to an asset: asset
blocks (maintenance, defect, inspection) prevent bookings — there is no second
availability for the same object and no need to create a rental contract.

**Units and buffers:** A resource with several units (e.g. four lanes) can be
booked partially; the quantity per event is checked against the units. Setup
and teardown buffers extend the booked window.

**Booking:** Resources are booked on the event or match day — with quantity,
optionally its own time window, buffers and the using person. The booking
checks capacity, hall/partial areas, room calendar, closures and asset blocks
in one transaction; two simultaneous bookings are never both confirmed. Away
venues are places and book nothing.

**Moving and cancelling:** When an event is moved, its bookings move along —
on a conflict everything stays as it was (old time, old booking). A
cancellation releases all bookings.

**Closures:** Weather, maintenance or external use are recorded as a closure
with a reason. Existing bookings are **flagged** for replanning, not deleted;
new bookings in the period are blocked. Lifting the closure releases the
flagged bookings.

**Clearances:** Boats, equipment and similar resources can require a briefing
or suitability clearance per member, which may expire and is granted by the
leadership. Without a valid clearance the person cannot be entered as user;
on the event, registered participants without clearance are shown.
