---
title: "GAEB bills of quantities"
topic: boq.overview
version: 1
audience: []
modules:
    - module.bau
related:
    - projects.manage
    - invoices.manage
---

Bills of quantities (BoQ) represent construction work in a structured
way — from the imported GAEB data exchange through measurement and
costing to exporting the current state.

**Import with preflight:** GAEB DA XML files of version 3.x are read in
exchange phases X81 to X86 (bill of quantities, cost estimate, request
for bid, bid submission, side bid, award). Before anything is written, a
preflight checks version, exchange phase, structure, uniqueness of the
reference numbers and the plausibility of quantities and units. Blocking
findings only produce an import log — nothing is written. A re-import
into an existing BoQ aborts if it would overwrite items that already
have execution or billing references.

**Structure & price states:** A BoQ consists of a header, hierarchical
sections with reference numbers and items with short and long text,
quantity, unit and unit price. Every import stores price snapshots, so
earlier price states remain traceable. A BoQ can be assigned to a
project; items can be linked to articles or material.

**Measurement & costing:** Progress records are captured additively per
item (quantity, source, note). Items with their first measurement
automatically switch to "in progress". The costing view compares planned
value (target quantity × unit price), executed value (measured quantity
× unit price), remaining work and degree of completion — it is an
analysis and does not replace invoicing.

**Workflow:** The BoQ and individual items follow directed status
transitions from tender through quotation and order to execution and
completion; invalid jumps are refused. Addenda are kept as separate
items, and the remaining-work view shows what is still open.

**Export:** The current BoQ state can be downloaded as GAEB DA XML in a
selectable exchange phase (default: award). The export is deterministic
and is logged with a content hash — the same state reproducibly yields
the same hash.
