---
title: "Legacy system"
topic: legacy.overview
version: 2
keywords:
    - old system
    - previous system
    - legacy data
    - data migration
    - on-call schedule
    - standby duty
    - call center login
    - archived entries
    - legacy archive
    - legacy users
    - legacy mode
    - legacy employees
related:
    - auth.login
    - admin.tenants
---

The legacy area is a bridge to the old system. It continues to provide
data and functions from the previous application until they are fully
migrated into WorkDiary. Access is only possible for users with an
assigned legacy identifier and for administrators.

The area covers:

- **Diary**: Weekly view as well as creating, editing and deleting
  legacy entries.
- **Emergency and on-call duty**: Planning and maintenance of emergency
  and on-call entries.
- **Archive**: Read access to archived diary entries including weekly
  and single views; archive runs can be triggered.
- **User administration**: Management of legacy users.
- **Call center**: A separate login with the emergency duty plan for
  call center access.

Read functions such as the weekly and archive views are always
available. Write actions (create, edit, delete) and changing the legacy
password are only enabled when write access to the legacy system is
active. Administrators additionally have a migration dashboard to import
data from the legacy system.

## Office and Employee

In **Legacy mode** – switchable under **Settings** in the header if you have
access to both areas – the main navigation shows **Week view**, **Work list**
and **Office**.

**Office** is the situation overview of the legacy system:

- Tiles **Problems**, **Open**, **Confirmed** and **Done (7d)** as well as
  **Overdue**, **Due today** and **Next 7d**; a click opens the work list with
  the matching filter.
- The **Weekly schedule** with **On-call** and **Standby**, starting with
  yesterday; browse with **Previous week**, **Next week** and **Current week**.
- **Weekend & holidays**, **New entries (14 days)**, **Top responsible
  (open)**, **Upcoming holidays (30 days)** and **Open notifications**.

Everyone sees the duty schedules. The diary data of all persons is visible to
legacy administrators and the **Accounting** role; everyone else sees only
their own. The call center login leads to the same page.

**Employee** is in the administration menu (the **Administration** icon in the
header) under **Personnel** and lists the users of the legacy system with
**Name** and **Email**:

- **New employee** creates a person with **Name**, **Email** and
  **Password**; when editing, the password stays unchanged if the field is left
  empty.
- The first three accounts of the legacy system (administrators) are not shown
  and cannot be changed here.
- **Delete** is only possible as long as no diary, on-call or standby entries
  exist for the person.
- Creating, changing and deleting require write access to the legacy system.

**Permission:** the **Employee** page is open to legacy administrators and
platform operations; the organization's administrator role alone is not
enough.
