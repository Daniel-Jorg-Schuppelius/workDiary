---
title: "Cost center rules"
topic: admin.cost-center-rules
version: 1
audience:
    - admin
related:
    - exports.payroll
    - org.teams
---

Cost center rules automatically assign a **cost center** to time
entries during the time export (e.g. for the payroll office) – so
nobody has to touch up each entry manually.

**Anatomy of a rule:** Every rule has exactly **one source** – either
a user **or** a team; if both are empty, the rule acts as the
**organization default**. In addition, each rule carries the cost
center code and a priority. Rules are maintained by administrators
and by accounting/payroll staff with the corresponding permission.

**Resolution order:** During export, rules are resolved per person
from most specific to most general:

- **User rule** – always wins when present.
- **Team rule** – applies if the person is a member of the team.
- **Organization default** – the rule without user and team.
- If no rule matches, the cost center stays **empty** in the export.

**Priority as tie-breaker:** If several rules qualify on the same
level (e.g. because a person belongs to multiple teams that each have
a rule), the rule with the **highest priority** wins; with equal
priority, the one created first. Use generous priority gaps (e.g.
steps of 100) so you can insert rules in between later.

**Interplay with master data:** Cost centers are maintained as master
data with a code and a label per organization. The rules currently
store the code as text – so make sure the codes in your rules match
the master data, and adjust the rules whenever you rename or
deactivate cost centers.

**Recommendation:** Start with an organization default, add team
rules for departments with their own cost center, and use user rules
only for genuine exceptions. After changes, run a trial export before
the data goes to the payroll office.
