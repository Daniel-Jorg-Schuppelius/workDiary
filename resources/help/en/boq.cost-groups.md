---
title: "Cost groups per DIN 276"
topic: boq.cost-groups
version: 1
audience: []
related:
    - boq.overview
---

Items carry **catalogue assignments**: the cost group states *what* the money is
spent on, the work category states *who* carries it out. Both usually arrive with
the contracting authority’s file — in German federal construction StLB-Bau is
mandatory as the basis of the specification, and StLB-Bau supplies the cost group
with every text variant.

**The catalogue master data is included.** Shipped are the DIN 276 cost groups in
editions **2018-12** (three levels) and **2008-12** plus the StLB work categories
— numbers and short designations only, no standard text.

**Both DIN editions stand side by side; they do not replace one another.** “310”
means “excavation” in the 2008 edition and “excavation, earthworks” in 2018, and
the 2018 edition regrouped the 200s, 500s and 600/700. A running project keeps
accounting by its own edition.

## Assigning

**Construction → bill of quantities → Assign.** The filter **“Only without cost
group”** is the actual working mode — what is assigned needs no review. Every row
shows the **origin**:

- *from the file* — came with the import and may be overwritten on reimport,
- *manual* — remains untouched on reimport,
- *suggestion* — set by a rule.

A code that is not in the catalogue is rejected. The report sums by code; a wrong
one would otherwise go unnoticed.

**Bulk assignment** over the selection also overwrites manual entries — whoever
triggers it means exactly that.

**Split items** appear with their partial quantities as separate rows below,
each with its own field. In the report the partial quantity's assignment beats
the item's.

## Suggestion rules

**Construction → Assignment rules** records which service usually maps to which
cost group. Two anchors:

- **Work category** — stated in the file and matched by prefix (“013” also
  matches “013.2”). The more reliable basis.
- **Keyword** in the short or long text — weaker, but the only handle when the
  contracting authority supplies no work categories.

The rule run **only fills gaps**: existing assignments remain, whatever their
origin. If several rules match, the lowest rank wins.

## Reporting

**Construction → bill of quantities → Cost groups** shows the totals per group,
switchable between the first, second and third level of detail, with a chart and
CSV/Excel output.

Three things matter:

1. **Partial quantities beat the item.** If an item is split (300 m³ to CG 310,
   150 m³ to CG 320), the split counts.
2. **The section is inherited** by items without an assignment of their own.
3. **“Unassigned” always appears in the table**, even at €0.00. The remainder of
   incomplete splits lands there too. A report that hides the remainder cannot be
   audited.

Below it stands **cost tracking**: tender scope, addenda, recorded work and
remainder. Addenda count separately from the tender scope — one was put out to
tender, the other was added. Recorded work above the tendered quantity yields a
**negative remainder**; it is shown, not smoothed. The **budget** comes from the
cost estimate on the project (see below); the **invoiced status** is
deliberately absent — it lives in the leading invoicing system.

## Cost estimates and budget

The German fee scale HOAI knows four stages — **cost estimate, cost
calculation, cost quotation, final cost statement**. They do not replace one
another: comparing them *is* cost control.

An external cost estimate arrives as **X51** and belongs to the **project**,
not to the individual bill of quantities — a building project is estimated as a
whole. It then appears as the budget column in cost tracking; without a project
that column stays empty, because a missing budget is not a budget of zero.

Only **cost quotation** (tender scope plus addenda) and **final cost
statement** (the recorded work) are produced. Estimate and calculation come
from the design stage; the benchmarks they require are not held here.

## Changing the edition

Changing the standard runs via **Assign → Change edition** and first shows a
**preview**. Only unambiguous counterparts are converted; everything else stays.
The gaps are the point — they show where someone has to decide. A guessed code
would be worse than the old one.
