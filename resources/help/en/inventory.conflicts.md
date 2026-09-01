---
title: "Inventory conflicts (external transfer)"
topic: inventory.conflicts
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - inventory.stock
    - warehouses.manage
---

If an external system holds inventory sovereignty (such as an ERP or
merchandise management system), WorkDiary mirrors every locally posted stock
movement to it. This page shows the cases where mirroring has permanently
failed — it is the place for deliberate follow-up work.

**Transfer with idempotency:** Each movement creates at most one delivery
job in a persistent queue. Even if the same operation is triggered several
times, only one transfer results — duplicate postings in the external system
are ruled out. Temporary errors are retried automatically.

**When a conflict arises:** If the delivery of a movement fails for good —
for example because the external system rejects it — a conflict is created.
The local posting remains in place, but the external stock now differs. Each
conflict appears here with a reference to the underlying movement and waits
for a conscious decision.

**Resolving:** There are two paths per conflict. *Keep local* explicitly
accepts the difference and closes the conflict without any further posting —
appropriate when the local state is correct in business terms.
*Compensate* offsets the local movement with an equal counter-posting in the
same stock. Nothing is ever deleted retroactively or technically rolled
back; the inventory ledger stays complete, and every decision is recorded
with person and timestamp.

**Permissions & filters:** Viewing requires the inventory read permission;
resolving additionally requires the posting permission, because the
compensation is a real stock posting. The list can be filtered by open or
all conflicts.

Open conflicts should be reviewed promptly: as long as they exist, local and
external stock differ — affecting availability, purchase proposals and
valuation.
