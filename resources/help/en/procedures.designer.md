---
title: "Procedure designer"
topic: procedures.designer
version: 1
audience: []
related:
    - procedures.run
---

The **procedure designer** lets you define mandatory workflows (work
instructions, checklists) that are later executed on orders.

## Template and versions

- A **template** has a unique **code**, a name and an optional domain
  (e.g. `it`, `hvac`).
- Steps always belong to a **version**. While a version is a **draft**, you
  can edit steps freely.
- **Publishing** marks the version valid and **immutable**. Corrections create
  a **new version** — running/old orders keep the version they used.

## Steps

Each step has a **type** (confirmation, measurement, photo, file, backup
proof, signature, approval …). Additional controls:

- **Required**: must reach a final status before the run can be completed.
- **Blocking**: blocks subsequent steps until it is done.
- **Four-eyes**: requires a second person to countersign.
- **Proof** (backup/photo/file/measurement/signature) and optional
  **role/qualification**.
- **Condition (if-then)**: the step only becomes relevant when another step
  has a given value/status.

## Automatic assignment

Via **order types** and **tags** you define which orders the template is
automatically suggested for. On the order detail page, matching published
templates appear as a start button.
