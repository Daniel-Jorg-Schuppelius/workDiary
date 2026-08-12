---
title: "Emergency attendance list"
topic: reports.presence-emergency
version: 1
audience: []
related:
    - reports.overview
---

The emergency attendance list shows, for a given point in time, who is
on site, off site or absent — intended for evacuations, fire incidents
and other emergencies. Without a time filter the current moment
applies; the point-in-time filter reconstructs past situations as far
as the data allows.

The groups are derived from existing data: "On site" from open
attendance clock-ins, "Off site" from running customer assignments and
time entries, "Absent" from approved vacation and active sick notes.
People without any signal appear under "No signal" — their status is
unknown and must be clarified on site.

The site filter maps people via attendance terminals. People who
clocked in without a terminal (e.g. in the browser) appear separately
as "Present without site mapping" and are never hidden — when in doubt,
a person belongs on the list rather than off it.

The list is a derivation, not a data source of its own: contradictions
are shown, never corrected automatically. Every access is logged; a
dedicated permission is required. A print view and PDF export are
available for posting — data timestamp and generation time are visible
on every printout.
