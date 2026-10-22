---
title: "Automations"
topic: admin.automations
version: 4
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
  with **Name**, **Trigger** (e.g. “Expense report submitted” or “Incoming invoice received”),
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

## Inbox rules (incoming invoices)

The trigger “Incoming invoice received” checks every new document of the
invoice mailbox. Besides the columns of the incoming invoice, conditions can
use these fields: `seller_name_key`, `buyer_name_key`, `party_name`, `party_name_key`, `sender_domain`, `mail_subject`, `attachment_names`, `file_name`, `country`, `kind_hint`. Name keys are lower case and without legal form,
so “Muster GmbH” and “MUSTER” count as the same; `kind_hint` names the
detected document type (for example `reminder` for a dunning letter).
Operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `in`, `not_in`, `contains`,
`not_contains`, `starts_with`, `ends_with`, `matches`. Example:
`{"all": [{"field": "sender_domain", "op": "=", "value": "example.com"}]}`.
Available actions: “File incoming invoice as “not an invoice””, “Assign
incoming invoice” and “Discard incoming invoice”. The rules page of incoming
invoices shows these rules for accounting; they can be switched and deleted
there, and both are recorded in the audit log.
