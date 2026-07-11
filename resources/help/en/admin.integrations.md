---
title: "Managing integrations"
topic: admin.integrations
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.lexoffice
---

This help applies to all integration admin pages – such as CalDAV,
WebDAV, Todoist, Zammad, Kimai/Clockify, mail intake, telephony,
team messengers, attendance terminals, shipping and SSO. All
connectors follow the same principles.

**Per organization:** Integrations are enabled and configured per
organization. Activation, credentials, health status and error
history always apply to the current organization only – the same
connector can be in a completely different state elsewhere.

**Credentials:** Tokens, passwords and device identifiers are stored
in the respective plugin configuration. Sensitive values are saved
encrypted and never appear in plain text again after saving – neither
in the UI nor in the audit trail.

**Health check and auto-disable:** Every connector is continuously
monitored for connection errors. If errors accumulate beyond the
configurable threshold, the connector is disabled automatically so it
cannot cause follow-up damage. Auto-disabled integrations stay
visible in the overview and are marked accordingly – once you have
fixed the cause (e.g. renewed an expired token) you can re-enable
them. A single faulty plugin never takes the application down with
it: errors are recorded in isolation.

**Incoming data – inbox first:** Imports never apply anything
blindly. Incoming records land in the integration inbox first, are
matched against existing data and are only applied after an
unambiguous match or your manual decision. Unclear cases and
conflicts remain as open inbox items until you resolve or discard
them.

**Outgoing changes – outbox:** Changes towards the external system
run through an outbox with automatic retry. If a delivery fails it is
attempted again; detected conflicts (e.g. when the external system
changed in the meantime) are routed back to the inbox for
clarification. Nothing gets lost and nothing is written twice.

**Recommendation:** After setting up a new connector, check its
health status, watch the inbox for unexpected conflicts for a few
days, and only then build automated workflows on top of it.
