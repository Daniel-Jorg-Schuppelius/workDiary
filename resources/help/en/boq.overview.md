---
title: "GAEB bills of quantities"
topic: boq.overview
version: 3
keywords:
    - BoQ
    - import BoQ
    - export BoQ
    - GAEB DA XML
    - X83
    - X86
    - tender
    - measurement
    - post-calculation
    - change orders
    - unit price
    - bid submission
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

## Preliminary notes and check before submission

If the GAEB file contains **preliminary notes** (contract texts ahead of the items), the bill of quantities view shows them in an expandable section, one text per block and unchanged as delivered. A reimport replaces them with the file. They are read from GAEB 90 and from DA XML (additional texts in the award block).

The **check before submission** also reports items priced in a currency other than the bill of quantities and a VAT amount that does not match the rate on the net total or, together with it, misses the gross total (to the cent).

## Bidder comments

In the request for bid, the client decides whether bidders may comment on items. The bill of quantities view shows what the file says. If comments are not permitted, the import does not take any over (the import log states how many) and the bid contains none. Reimporting the bid does not change the setting from the request.

## Progress with a record and import findings

Besides the quick quantity entry, the tape-measure icon of an item opens the dialog **Report progress**. There you additionally choose a **material consumption** of the project or a **protocol** of the project. The chosen record determines the source of the report and stays with it; the dialog shows the previous reports with their reference. A material consumption can be assigned to exactly one item; its cost counts towards this item in the economics evaluation. Enter the quantity 0 to assign costs only.

The **import history** shows the findings of each run. A rejected reimport appears there with the status “conflict” and states, for each protected item, quantity and unit price on record next to the values of the file. It changes nothing in the bill of quantities itself.

The protocol itself also offers **Report progress for a bill-of-quantities item** in its menu if the project of the protocol has a bill of quantities with open items. The report receives the source “protocol” and refers to this protocol.
