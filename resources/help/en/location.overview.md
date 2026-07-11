---
title: "Location-based time tracking"
topic: location.overview
version: 1
audience: []
related:
    - time-entries.start
    - attendance.manage
---

Location-based time tracking automatically suggests time bookings when a
device enters and leaves a registered customer site. It complements manual
recording — nothing is ever booked automatically, only after explicit
confirmation.

**Geofences per customer site:** For each relevant customer site, a zone is
defined by a centre point and radius. Stays only arise within these zones;
movement outside them has no business meaning.

**Data sources:** Position reports come either from the OwnTracks or Traccar
apps via a personal device access, directly from the browser, or
retrospectively by importing a Google location history file. Every device is
registered deliberately, and tracking requires the documented consent of the
person concerned.

**From signal to suggestion:** Incoming points are condensed into stays:
entering and leaving a geofence produces a visit with a start and an end.
Completed visits appear as suggestions in a personal review inbox — with the
customer, the project where applicable, and the recorded period.

**Review instead of automation:** Only confirming a suggestion creates an
actual time entry; unsuitable suggestions can be dismissed. Between the
location signal and the booking there is always a conscious decision by the
person concerned.

**Privacy:** What is evaluated are entry and exit events at the registered
customer sites — there is no permanent location surveillance. Each person
sees only their own movement trail and their own suggestions; even
administrators have no access to them. Raw location points are stored
encrypted and deleted automatically after a retention period (90 days by
default). Confirmed time entries and the reports derived from them remain
unaffected — only the raw trail disappears, not the working time.
