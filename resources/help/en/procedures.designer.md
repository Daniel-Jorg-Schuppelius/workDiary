---
title: "Procedure designer"
topic: procedures.designer
version: 4
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

## Configuration by step type

Depending on its **Type**, a step shows further fields:

- “Choice”: **Choice options**, comma-separated. Without options the step
  takes free text during the run.
- “Number/measurement” and “Measurement series”: **Unit**, **Lower limit**
  and **Upper limit** (tolerance).
- “Wait time”: **Fixed waiting time (seconds)**; if empty, the duration is
  entered during the run.
- “Backup record”: **Scope**, **Storage**, **Verification method**,
  **Minimum size (KB)** and **Maximum backup age (minutes)**. “Chosen
  during the run” leaves the entry to the executing person.
- Any other step: **Prior backup required** blocks it until a backup step
  of the same run is done and its proof confirmed, optionally with a
  maximum age.
- Four-eyes steps: **Role of the second person**, **Qualification of the
  second person** and **Executing person may countersign themselves**
  (normally off).

**Qualification** means the abbreviation of an employee qualification, or
its name; the check is whether the person currently holds it. The **Step
library** offers the same fields under **Configuration by step type**.
Entries from industry profiles or imports that the designer does not show
are kept when saving.

The risk level “Critical” requires at least one four-eyes step: without it
the version cannot be published, and a run is only completed once a
four-eyes approval is done.

## Automatic assignment

A template is suggested when **all** criteria set match: **Order types**,
**Tags**, **Customers**, **Asset categories**, **Products** and **SLA
contracts**. Category, product and SLA refer to the asset of the order.
Without criteria the template applies to every order.

- On the order detail page, matching published templates appear in the
  **Procedures** card under “Procedures suggested for this order:” as a
  start button.
- If the template names an asset category, a product or an SLA contract,
  it is also offered on the asset record under “Procedures intended for
  this asset:”.
- **May run on blocked assets** allows starting on a blocked asset, for
  repair and inspection procedures for example.
