---
title: "Learning standards: SCORM, cmi5 and LTI"
topic: learning.standards
version: 1
audience: []
related:
    - learning.overview
    - training.overview
    - admin.integrations
---

Besides its own learning units, the platform understands the common exchange
formats, so you can import purchased courses and launch your own courses in
other systems.

**SCORM 1.2 and 2004** — A SCORM package is a ZIP with a manifest. On upload
it is validated and extracted; executable files and paths pointing outside the
package are rejected. Course content runs on a **separate content host** so
that foreign course code is not executed in the application's own origin.
Progress and completion are taken from the package's messages.

**cmi5 and xAPI** — cmi5 courses report activity as statements to the built-in
learning record store. A session only accepts statements within a limited time
window; afterwards it is closed.

**LTI 1.3** — The platform works in both directions: it can embed external
tools as a learning unit **and** be launched from another learning management
system. Launches use signed tokens; keys are rotated regularly, and previous
keys stay valid for verifying running sessions.

**Limits:** For all three routes the course's completion rule applies, not the
package's. A package may report completion — whether it counts is decided by
the course's release settings.
