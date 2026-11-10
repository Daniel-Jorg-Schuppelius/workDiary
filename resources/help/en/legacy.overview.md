---
title: "Legacy system"
topic: legacy.overview
version: 5
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
    - support portal
    - portal customers
    - legacy customer orders
    - mail notice
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
- **Support portal**: Customer orders and accounts from the customer
  portal of the legacy system.

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
legacy administrators, platform operations and the **Accounting** role;
everyone else sees only their own. The call center login leads to the same
page.

**New entry** is available to everyone whose user account is linked to an
account in the legacy system; only legacy administrators create entries for
other persons. Archiving, emergency and on-call duty are maintained by legacy
administrators and platform operations.

**Employees** is in the administration menu (the **Administration** icon in the
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

**Permission:** the **Employees** page is open to legacy administrators and
platform operations; the organization's administrator role alone is not
enough.

## Support portal

**Support portal** in the main navigation of legacy mode shows the orders that
customers placed in the customer portal of the legacy system:

- Filter by **Year** (the most recent year with orders is preselected),
  **Customer** and search text; the tiles **New**, **Viewed**, **Appointment
  set**, **Appointment passed**, **Done** and **Archived** filter the list.
- **Edit** maintains customer, phone, site, order description, **Appointment**,
  **Technician**, **Message for the customer**, **Internal note**, **Duration**
  and **Status**. A new appointment sets the status to **Appointment set**
  unless done or archived is selected. The customer sees the message, not the
  note.
- **Create order** records an order for a customer, for example after a phone
  call.
- **Mail notice** switches the notification for new customer orders and sets
  the recipient.
- **Archive** lists archived orders; **Archive** moves orders with the status
  **Archived** dated before a cut-off day there. Customers no longer see them
  afterwards.

**Portal customers** in the management menu maintains the customer accounts
with **Company**, **Username** and **Password** (4 to 10 characters). Customers
with open orders cannot be deleted.

The sign-in address for your customers is shown at the top of the **Portal
customers** page; **Copy** puts it on the clipboard.

Customers sign in on the customer portal's own sign-in page, see only their own
orders with appointment and replies, place new orders and change their
password. If write access to the legacy system is off, the portal is
read-only.

**Permission:** Support portal and portal customers are open to legacy
administrators and platform operations.
