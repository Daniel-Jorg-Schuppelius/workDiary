---
title: "Connect DomainReselling"
topic: admin.domain-provider
version: 2
keywords:
    - domain management
    - manage domains
    - domain reseller
    - DNS
    - DNS records
    - domain registration
    - domain renewal
    - registrar
    - domain portfolio
    - subuser
audience:
    - admin
    - geschaeftsfuehrung
modules:
    - module.domain
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

**Renew credentials:** The key icon opens the “Renew credentials” dialog.
Enter a new password; login and default user stay unchanged if you leave
the fields empty. WorkDiary checks the new credentials straight away — the
connection remains in place with all domains, assignments and evidence.

**Disconnect:** “Disconnect” deletes nothing. The password is removed, the
status changes to *disconnected*, and no synchronisation, event polling or
command reaches the provider any more. Domains, contacts, DNS zones, the
command journal (who changed what at the provider), the bookings and the
domain links of your reselling subscriptions and purchase lines are kept as
evidence. To reconnect, store new credentials via “Renew credentials”; once
the check passes, the connection is active again.

**States:** A connection is *draft*, *active*, *blocked* or
*disconnected*. Blocked connections surface a visible blocked state in the
health check — never a silent misfire. The health check does not report a
disconnected connection as a fault; it is deliberately out of service.
