---
title: "Free time dimensions"
topic: admin.time-dimensions
version: 1
audience: []
related:
    - reports.overview
---

Free time dimensions extend time allocation with custom targets that do
not exist as a WorkDiary model — such as ERP orders, plant numbers or
internal clearing objects. Existing master data (projects, cost
centers, sites, vehicles, activities) is always referenced directly and
never mirrored as a dimension.

A dimension type bundles related values under a name and code.
Disabled types disappear from the split dialog; existing allocations
and reports remain unchanged. Values may carry a validity period —
outside of it they are no longer offered in the dialog.

The external ID per value anchors a future automatic synchronization
from an external system (e.g. ERP cost objects). Until then, values are
maintained manually here; creation, toggling and deletion are audited.

In the "Time allocation by dimension" report each dimension type forms
its own group with its values.
