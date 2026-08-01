---
title: "Print orders (print & copy shop)"
topic: print.orders
version: 1
audience: []
related:
    - claims.overview
    - documents.manage
---

The print/copy-shop industry profile keeps every print order as a
specialised file attached to a manufacturing order: data intake, file check
(preflight), print approval, production, quality control and hand-over
belong together reproducibly.

**File & preflight:** The production file lives in the document store and is
bound to the order with its SHA-256 checksum. Preflight distinguishes errors
(which block approval) from warnings; a manual override requires a reason
and is audited. A new file version automatically resets the order to
"data check".

**Approval:** The print approval freezes format, material, quantity, colour
mode, due date and finishing together with the file hash as an immutable
production snapshot.

**Production & QC:** Machines that are blocked or have overdue inspections or
calibration cannot start regularly. Good quantity and waste flow through the
manufacturing order into inventory and post-costing. Quality control
compares against the approved state and documents release, hold or rework.

**Hand-over & retention:** Pickup requires a hand-over record, shipping uses
the existing shipment logic, and counter sales stay data-minimal. When the
retention period expires only the customer file is removed — order, snapshot
and checksum remain as commercial evidence.
