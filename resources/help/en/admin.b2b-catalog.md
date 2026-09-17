---
title: "Manage B2B catalogue access"
topic: admin.b2b-catalog
version: 1
audience:
    - admin
related:
    - admin.integrations
    - supplier-catalogs.overview
    - articles.master
---

B2B catalogue access lets business customers pull articles and prices directly
from your stock — either as a cart handover from their purchasing system or as
a catalogue file.

**Access credentials:** You issue one per customer. The secret is shown
**once** and never again — note it immediately. Lost credentials are not
recovered but **rotated**; the previous one becomes invalid. Revoke access that
is no longer needed.

**Releases:** Only what you release is visible. You can store a customer price
per article; without one, the regular price applies.

**Orders:** Orders coming back from the purchasing system appear in the
overview and are checked like any other incoming data before they become a job
— nothing is adopted blindly.

**Catalogue file:** Instead of the cart route, you can export the released
stock as a catalogue and price file that common ERP systems can read.

**Security:** The customer pages depend solely on the access secret. Treat it
like a password and rotate it when staff at the customer change.
