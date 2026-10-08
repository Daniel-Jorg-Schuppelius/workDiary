---
title: "Maintaining form templates"
topic: forms.templates
version: 3
keywords:
    - create form
    - form builder
    - form designer
    - create checklist
    - form fields
    - field types
    - dropdown field
    - required field
    - activate form
    - archive form
    - custom forms
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

Form templates define checklists and records without code – via field
definitions. You find them under **System** → **Rules & processes** →
**Form templates** or via the **Form templates** button in the **Forms**
overview.

Typical workflow:

1. **Create template**: **Name**, **Description**, optionally **Valid
   from** and **Valid until** as well as **Assignment: entry type** and
   **Assignment: customer** (with “all” the template applies everywhere).
   Below follow the **Fields**; **Add field** adds another one. Per field:
   **Field label**, **Field type** and **Required**, depending on the type
   the **Options** (comma-separated), **Unit** or **Value range** (Min,
   Max), optionally a **Help text** and a **Visible when** condition that
   only shows a field once another field has a certain value.
2. **Activate**: new templates start in status “Draft”; only in status
   “Active” can the template be filled in.
3. **Archive**: removes the template from the fill-out selection –
   completed forms remain readable. An archived template can be
   activated again.

Field types: “Text”, “Multi-line text”, “Number”, “Checkbox”, “Choice”,
“Multiple choice”, “Date”, “Date and time”, “Scale”, “Photo”, “File”,
“Signature”, “Section” and “Measurement”. You do not enter a field key
of your own; each field label may occur only once per template.

Important statuses: “Draft” → “Active” → “Archived”.

Snapshot principle: every completed form freezes the field definition at
the time of filling. Field changes therefore affect **newly completed
forms only** – old ones remain unchanged and evaluable. Even **Delete**
on a template does not make completed forms unreadable.

Permissions: anyone with the **Manage form templates** permission
(team leads by default) may create, edit, activate, archive and delete
form templates.

Tip: the system derives a field's internal assignment from its field
label. Keep field labels unchanged if you want to compare completed
forms across several versions of a template.
