---
title: "Conflicts with external systems (stock and articles)"
topic: inventory.conflicts
version: 3
keywords:
    - stock discrepancy
    - sync error
    - synchronization conflict
    - failed sync
    - ERP sync
    - compensating entry
    - article conflict
    - reconcile stock
    - data mismatch
    - Lexware Office
    - Lexoffice
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
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

**Article conflicts:** The same list shows articles that were changed
locally and whose state in the connected external system (such as Lexware
Office) differs — with the plugin's conflict strategy set to “manual
review”. For each conflict, the article, the differing fields and both
values are shown side by side. Three paths: *Keep local* closes the
conflict; the local state stays and is transferred to the external system
at the next synchronisation. *Take the external state* (for example “Take
Lexoffice state”) fetches the article afresh from the external system and
overwrites the local change. *Dismiss* closes the conflict without
reconciliation — both states stay as they are; if the article still
differs at the next synchronisation, a new conflict is created.

**Permissions & filters:** The “Conflicts” tab in the inventory tab bar
shows the number of open conflicts. Viewing requires the inventory read
permission or the article read permission; without the inventory
permission you only see article conflicts, without the article permission
only stock conflicts. Resolving depends on the type: stock conflicts need
the posting permission, because the compensation is a real stock posting;
article conflicts need the article management permission. The list can be
filtered by open or all conflicts and by type (stock, article).

Open conflicts should be reviewed promptly: as long as they exist, local and
external stock differ — affecting availability, purchase proposals and
valuation.
