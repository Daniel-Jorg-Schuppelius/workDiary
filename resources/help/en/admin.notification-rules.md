---
title: "Notification rules"
topic: admin.notification-rules
version: 3
keywords:
    - escalation
    - configure notifications
    - email notification
    - push notification
    - recipients
    - reminders
    - overdue alerts
    - deadline monitoring
    - alerting
    - notification channels
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
related:
    - admin.handbook
    - communication.notes
    - glossary.core
---

Notification rules define per event type **who** is informed via
**which channels** – and when escalation happens. The list shows the
columns **Active**, **Channels**, **Recipients** and **Escalation** for
each **Event**; events without a rule of their own carry the note
**Default (not customised yet)**.

Typical workflow:

1. Choose **Edit** for the event (e.g. open issue assigned/due
   soon/overdue, follow-up due, document expiring, correction request,
   monthly approval submitted, ISMS certificate expiring, corrective
   action overdue, risk review due). The **Edit notification rule**
   dialog opens.
2. Under **Active**, switch on **Notifications for this event enabled**
   and choose the **Channels**: **In-app**, **E-mail**, **Push**,
   **Microsoft Teams**, **Mattermost** or **Calendar**. For critical
   events (e.g. **Crisis alert**, **On-call assigned**, **Critical
   safety event**) **SMS** is available as well.
3. Define the **Recipients**: **Notify the affected person (e.g.
   assignee or requester)**, **Recipient roles** (e.g. team lead) and
   **Additional fixed recipients**.
4. For overdue events, optionally set up **Escalation**: switch on
   **Escalation enabled**; after **Escalate after (hours)** (1–720) the
   **Escalation role** is notified in addition. **Escalation level 2**
   and **Escalation level 3** each notify their own roles and fixed
   recipients after further hours.

Good to know:

- Without a rule of its own, the displayed default of the event applies
  (channels, affected-person flag, roles) – you only need to configure
  deviating cases.
- **Microsoft Teams** and **Mattermost** post to the organization's
  configured chat channel; **Calendar** adds date-related events to the
  organization's connected calendars (CalDAV/Microsoft 365/Google).
  **SMS** only reaches people with a confirmed mobile number and costs
  money per message.
- Escalation exists only for overdue/expiry events.
- Some events are triggered immediately (e.g. assignment), others are
  found by the deadline scanner (e.g. “due soon”).

Permission: people with the **View notification rules** permission see
the list; only people with **Edit notification rules** may change it.
