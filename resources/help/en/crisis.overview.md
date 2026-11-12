---
title: "Emergency & crisis management"
topic: crisis.overview
version: 4
keywords:
    - crisis team
    - emergency management
    - business continuity
    - BCM
    - alerting
    - crisis alert
    - situation report
    - reporting deadline
    - NIS2
    - data breach notification
    - disaster recovery
    - crisis exercise
audience: []
modules:
    - module.crisis_management
related:
    - documents.manage
---

The crisis case file runs a coordinated command state on top of linked
specialist records (service tickets, security/privacy incidents, safety
events, playbook runs) — the specialist modules stay leading.

**Crisis team & alerting:** roles with person, deputy and contact info.
Alerting deliberately overrides quiet hours (crisis alert); every member
acknowledges their alert, unacknowledged alerts are escalated to the
deputy. Being named to the team is the audited emergency access to the
case file.

**Situation & decisions:** situation reports are versioned and
append-only; the decision log records time, decision and rationale.

**Reporting deadlines:** deadline templates per category (GDPR 72 h,
NIS2 24 h/72 h/1 month, KRITIS without delay) are catalogue data — the
organisation can override them; concrete deadlines run from activation.

**Discarding a false alarm:** If a reported crisis turns out to be a false
alarm before anyone has assessed the situation, “Discard as false alarm”
closes the file with a mandatory reason. The reason is kept in the audit
log and the file stays read-only. Once assessed, a crisis is no longer
discarded but given the all-clear.

**Communication:** draft, approval (never by the author) and documented
dissemination stay separately traceable.

**Recovery & review:** critical processes with RTO/RPO, workarounds and
substitute processes; after the all-clear the post-review follows
(mandatory before closing). Exercises run separately and never distort
real case files.

You maintain your own reporting deadlines on the **Reporting deadlines** tab:
adopt a category’s defaults as own deadlines, add, change and deactivate
deadlines. As soon as a category has an active own deadline, only the own
deadlines apply to it; without an active own deadline the defaults apply
again.

**Actions with a predecessor:** When recording an action you may choose a
predecessor from the same file. The action can only be completed once the
predecessor is done or cancelled; you can start working on it earlier.

**Substitute process and residual status:** For a critical process you record
the substitute process in addition to the workaround. With the recovery status
you note the remaining limitation.

**Opening a crisis from an incident:** Security incidents, data protection
incidents, occupational safety events and major-incident tickets carry the
“Open crisis” button. The dialog prefills title, category and trigger and
links the incident to the new file. You link further records in the file via
“Link a record” from a selection; only what you may read is offered.

**Emergency attendance list:** With the right for the emergency attendance
list, an active crisis file leads straight to it.

**Change predecessor:** You change or remove a measure’s predecessor directly in the list of measures. Only measures of the same case can be chosen; WorkDiary rejects a choice that would create a cycle (A waits for B, B for A).
