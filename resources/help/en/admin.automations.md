---
title: "Automations"
topic: admin.automations
version: 3
keywords:
    - workflow
    - rules
    - if-then rule
    - trigger
    - rule engine
    - automated action
    - workflow automation
    - create rule
    - process automation
    - automate tasks
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Automations are rule-based flows following the pattern
**event → condition → action**. When a defined trigger event occurs
and the configured conditions match, the assigned action is executed.
Rules apply per organization and are strictly limited to your own
tenant. Every evaluation is recorded in the audit log.

The overview lists all rules with **Prio**, **Name**, **Trigger**,
**Action(s)** and **Active**, sorted by priority by default. The
following actions are available:

- **Create new rule (JSON)**: opens the **New automation rule** dialog
  with **Name**, **Trigger** (e.g. “Expense report submitted”),
  **Action** (e.g. “Approve expenses”), **Priority** and **Conditions
  (JSON)**. The action must match the trigger; an empty condition
  always applies. **Create rule** saves the rule.
- **Deactivate**/**Activate**: deactivated rules are kept but no longer
  trigger any actions.
- Detail view (click on the name): shows the trigger, conditions and
  actions as well as the **Audit log (last 50)** with **Time**,
  **Subject**, **Decision** and **Log**.
- **Delete**: removes the rule permanently.

The **Priority** controls the order when several rules belong to the
same trigger (lower value first, default 100). Only the first matching
rule is executed; a rule fires at most once per record. Invalid JSON in
the conditions is rejected.

Permission: automations are managed by the organization's
administrators.

Note: for plain notifications, the **Notification rules** are often the
simpler choice; for external systems see **Webhooks**.
