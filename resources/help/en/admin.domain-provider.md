---
title: "Connect DomainReselling"
topic: admin.domain-provider
version: 1
audience:
    - admin
related:
    - admin.plugins
    - admin.integrations
---

WorkDiary connects a **DomainReselling account** per organisation and
manages its domains in a controlled way: read the portfolio, assign
customers, maintain terms and DNS, and gate high-risk actions behind
approval. This page sets up the connection — the actual domain work then
happens in the "Domains" module.

**Choose the environment:** Every connection runs either in *OT&E* (the
test/pilot environment) or in *production*. New accounts start in OT&E;
production is unlocked only after a passed, really confirmed pilot — so no
live registration ever slips into a test by accident.

**Credentials:** Login and password are stored encrypted and never appear
in URLs, logs or diagnostics. Optionally set a default user (s_user) — the
context under which an authorised subuser's commands run.

**Test & synchronise:** "Test connection" checks the credentials against
the API without changing anything. "Synchronise" pulls the current
portfolio (domains, terms, renewal modes, resellers/subusers) into the
local projections. The sync is read-only and idempotent.

**Confirm the pilot:** After a successful real test you confirm the pilot;
only then can the connection be switched to production. While the pilot is
open, the health check reports "pilot open".

**Rotate credentials & disconnect:** Login/password can be reset at any time
(rotation) without recreating the connection. Disconnecting removes the
connection; the projection data already read is kept as evidence.

**States:** A connection is *draft*, *active* or *blocked*. Blocked
connections surface a visible blocked state in the health check — never a
silent misfire.
