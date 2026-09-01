---
title: "Public tender radar"
topic: tenders.radar
version: 1
audience: []
modules:
    - module.applications
related:
    - applications.overview
---

The radar scans the **German federal public procurement notices** for tenders
that match your own business. The source is the official notice service
(oeffentlichevergabe.de), which publishes all mandatory notices as open data
under CC0 — no registration and no portal credentials required.

**Filter profiles** define what is searched for. Two code systems carry the
search: **CPV** states *what* is being procured, **NUTS** states *where*. Both
are hierarchical, so prefixes are enough — `45` matches all construction work,
`DEA` all of North Rhine-Westphalia. Keywords additionally search title,
description and contracting authority; **excluded keywords weigh more**: a hit
there discards the notice even if everything else matches. Notices without a
stated value are never excluded by the value limits — otherwise anything that
does not state its value would be lost.

**The fetch runs daily and retrieves the previous day.** A publication day is
only complete on the following day; fetching today would leave gaps. Corrected
notices arrive as a new version, the old one is retained.

**The match inbox proposes, it does not decide.** What does not fit is hidden
and kept as evidence; what fits is turned into a tender case with title,
contracting authority, CPV, region, deadline and source pre-filled. **Check
the procedure type and threshold afterwards** — the open data source names the
procedure only coarsely, and neither the German procedure type nor the
threshold situation can be derived from it reliably.
