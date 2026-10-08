---
title: "Procedure designer"
topic: procedures.designer
version: 3
keywords:
    - work instruction
    - create checklist
    - SOP
    - standard operating procedure
    - workflow template
    - process template
    - mandatory steps
    - four-eyes principle
    - conditional step
    - publish version
audience: []
related:
    - procedures.run
---

The **Procedure designer** lets you define mandatory workflows (work
instructions, checklists) that are later executed on orders. You find the
templates under **System** → **Rules & processes** → **Procedure
templates**; **Edit** opens a template's designer.

## Template and versions

- A **Template** (**New template**) has a unique **Code**, a **Name**,
  an optional **Domain** (e.g. `it`, `hvac`) and a **Description**; the
  designer adds the **Risk level**.
- Steps always belong to a **Version**. While a version is a **Draft**,
  you can edit steps freely and keep them with **Save**; a **Change
  note** records what has changed.
- **Publish** marks the version valid and **immutable**. Corrections
  require a **New version** — running and old orders keep the version
  they used.

## Steps

**Add step** or **Insert from library** (from the **Step library**) adds
steps. Each step has a **Code**, a **Label**, an optional
**Description** and a **Type**, such as “Confirmation”, “Text”,
“Number/measurement”, “Choice”, “Photo”, “File”, “Backup record”,
“Signature”, “Material entry”, “Measurement series”, “Approval
(four-eyes)” or “Wait time”. Additional controls:

- **Required**: must reach a final status before the run can be
  completed.
- **Blocking**: blocks subsequent steps until it is done.
- **Four-eyes**: requires a second person to countersign.
- **Proof** (“Backup”, “File”, “Photo”, “Measurement”, “Signature” or
  “None”) and optionally **Required role** and **Qualification**.
- **Condition: step** and **Condition: value** (if-then): the step only
  becomes relevant when another step has a given value.

## Automatic assignment

Via **Order types** and **Tags** you define which orders the template is
automatically suggested for. On the order detail page, matching published
templates appear in the **Procedures** card under “Procedures suggested
for this order:” as a start button.
