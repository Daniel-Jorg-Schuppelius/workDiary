---
title: "Industry profiles"
topic: admin.branch-profiles
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.import
---

Industry profiles bring a curated template package per trade into the
tenant in one step – instead of creating every order type, category and
checklist by hand.

A package may include:

- **Order types** and **categories** (classifications per domain such as
  activity, defect type, root cause, result, product group).
- **Requirement rules** that enforce specific categories per order type on
  creation or before completion.
- **Checklists / procedure templates** (e.g. electrical safety check, SHK
  pressure test, cleaning quality control) – published and ready to use.
- **Room requirements** as organisation-wide templates (e.g. hygiene level,
  technical inspection, access restriction) that can be applied when
  maintaining a room.
- **Default tags** plus – depending on the trade – maintenance plans, SLA
  templates, cleaning profiles and a software catalogue.

How to use it:

1. Find the matching trade in the catalogue (search / filter by install
   state).
2. The **content preview** on the card shows what the package brings:
   counts of order types, categories, requirement rules, checklists, room
   requirements and tags, plus a list of the included order types and
   checklists.
3. Choose **Install** and confirm.

Good to know:

- Installation is **idempotent**: re-installing creates no duplicates and
  never overwrites **locally customised data**.
- **Re-apply** resets imported templates (classifications, requirement
  rules, room requirements) back to the profile state. Already **published
  checklists** are preserved – checklists are never overwritten
  automatically.
- Every installation is recorded in the tamper-evident audit log.
- Profiles are stored as configuration; new trades can be added without
  code changes.

Multiple profiles, primary profile and uninstall:

- Profiles can be **combined** (e.g. electrical and HVAC). The first one
  installed is the **primary profile**; navigation focus and domain
  defaults follow it, while the module recommendation in the scope unites
  all installed profiles. **Set as primary profile** switches it
  deliberately.
- **Uninstall** (platform admin) removes unused order types, categories and
  tags of the profile, deactivates classifications in use and deletes the
  profile's requirement rules. Checklists, maintenance plans, SLA, cleaning
  and room templates remain as master data.
- An **update badge** on the card indicates a newer profile version;
  "Re-apply" adopts it.
- **Import** accepts a JSON profile from the catalogue; unknown
  classification domains or modules are rejected.
- Order types ship in the enabled languages; the display follows the
  user's language while the source label stays editable.
