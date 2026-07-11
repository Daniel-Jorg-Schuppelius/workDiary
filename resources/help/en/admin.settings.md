---
title: "System settings"
topic: admin.settings
version: 1
audience:
    - admin
related:
    - admin.handbook
---

This page manages all registered settings of the platform in one
place – from page sizes and upload limits to operations and
integration thresholds.

**Central registry:** Every setting is registered with its type,
allowed scopes and validation rules. Writes go exclusively through
this validated path – invalid values (e.g. outside the min/max
bounds) are rejected with a clear error message before they can take
effect.

**Two scopes:** Depending on the entry, settings apply
**system-wide**, **per organization**, or both. The scope switcher
changes the view; the search filters by key, and the list is grouped.

**Precedence logic:** A fixed order applies to every value – the
**organization setting** takes precedence over the **system
setting**, which in turn takes precedence over the installation's
built-in **default**. The overview shows the effective value and its
origin for every entry, so you can immediately tell whether a value
is the default or has been overridden.

**Reset and history:** Every override can be reset to the default
individually. For system settings you can additionally inspect the
change history: who set which value and when – traceable through the
audit trail.

**Sensitive values:** Entries marked as sensitive (e.g. webhook
addresses containing secrets) are displayed masked in the UI. They
can be set anew but not read back.

**Effect on jobs:** Some settings influence scheduled background jobs
(such as retention periods or execution times). These relationships
are noted on the entry; the change takes effect on the next run.

**Recommendation:** Override as little as possible. Every org-level
override makes behavior harder to predict – set one only when the
organization really has to deviate, and document the reason. After a
change, verify the displayed effective value instead of trusting the
input.
