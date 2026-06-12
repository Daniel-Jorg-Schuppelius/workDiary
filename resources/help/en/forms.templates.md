---
title: "Maintaining form templates"
topic: forms.templates
version: 1
audience:
    - admin
    - teamleitung
related:
    - forms.fill
    - glossary.core
---

Form templates define checklists and records without code – via field
definitions.

Typical workflow:

1. **Create a template**: name, description and fields. Per field:
   key, label, type ("Text", "Multi-line text", "Number", "Date",
   "Select", "Checkbox"), required yes/no, options for selects,
   optionally help text and a unit.
2. **Activate**: the template can only be filled in while in status
   "Active".
3. **Archive**: removes the template from the fill-in selection –
   existing submissions remain readable.

Important statuses: "Draft" → "Active" → "Archived".

Snapshot principle: every submission freezes the field definition at
the time of filling. Field changes therefore affect **new submissions
only** – old ones remain unchanged and evaluable. Even deleting a
template does not make existing submissions unreadable.

Permissions: form templates are created, edited, activated, archived
and deleted by team leads or other explicitly authorized staff.

Tip: keep field keys stable if you want to compare evaluations across
several template generations.
