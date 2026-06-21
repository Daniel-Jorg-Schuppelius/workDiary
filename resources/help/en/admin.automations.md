---
title: "Automations"
topic: admin.automations
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.notification-rules
    - admin.webhooks
---

Automations are rule-based flows following the pattern
**event → condition → action**. When a defined trigger event occurs
and the configured conditions match, the assigned actions are
executed. Rules apply per organization and are strictly limited to
your own tenant.

The overview lists all rules, ordered by priority and name. The
following actions are available per rule:

- **Create**: name, trigger event, conditions and actions.
  In the current MVP, conditions and actions are entered as JSON; a
  visual rule editor is planned as a later extension.
- **Toggle active/inactive**: disabled rules are kept but no longer
  trigger any actions.
- **Detail view**: shows a rule's most recent executions (runs) for
  traceability.
- **Delete**: removes the rule permanently.

The **priority** controls the order when several rules match (lower
value first). Invalid JSON in conditions or actions is rejected.

Note: for plain notifications, **notification rules** are often the
simpler choice; for external systems see **webhooks**.
