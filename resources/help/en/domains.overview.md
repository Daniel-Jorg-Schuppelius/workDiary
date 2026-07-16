---
title: "Domain management"
topic: domains.overview
version: 1
audience: []
related:
    - admin.domain-provider
    - contacts.manage
---

The module manages the domains of a connected DomainReselling account as an
auditable portfolio — from customer assignment and term through
nameservers/DNS to renewal, transfer and bookings. The connection itself is
set up under "DomainReselling" in the administration area.

**Portfolio:** The overview lists every domain with customer, expiry,
renewal mode, registrar, transfer lock and currency of the data. The figures
at the top show expiry within 90 days, risky modes (autoexpire/autodelete),
domains without a customer assignment, and sync/conflict cases. Filter by
domain name, TLD, sync state, renewal mode and expiry corridor.

**Customer assignment:** Each domain can be assigned to a customer
(internally via its Sqid identifier). Unassigned domains stay visible in the
figure so the portfolio is kept complete.

**Detail view:** The domain page bundles overview, nameservers & DNS,
invoices, timeline and actions. "Refresh" reconciles the provider state for
exactly this domain.

**DNS:** The zone is read on demand; records can be replaced or modified
selectively. After a write the system detects deviations (DNS conflict) and
surfaces them instead of overwriting. MX/SRV records require a priority.

**Registration:** Availability is checked before registering. A registration
needs a customer, an owner-contact handle, at least two nameservers and an
explicit price confirmation.

**Term & transfer:** Setting the renewal mode, renewing manually, setting or
releasing the transfer lock and starting a transfer-in run as logged
commands with a status history (draft → sent → confirmed).

**High-risk actions:** Deletion, push to another user, trade (owner change),
transfer-out and object assignment are gated: they require re-typing the
domain name and a four-eyes approval. Submitted actions appear for approval
or rejection; the provider state is reconciled after execution (conflicts
are flagged).

**Bookings & reports:** The booking view is a read-only journal — not a tax
invoice. The reports bundle expiry corridor, renewal cost forecast, customer
assignment, risk modes and invoice coverage.

**Resellers/subusers:** The reseller view shows the subuser hierarchy with
portfolio, balances and level, and allows customer assignment per subuser.
