---
title: "Domain management"
topic: domains.overview
version: 5
keywords:
    - register domain
    - renew domain
    - DNS records
    - DNS zone
    - change nameservers
    - MX record
    - domain transfer
    - transfer lock
    - owner change
    - domain expiry
    - domain reseller
    - domain portfolio
audience: []
modules:
    - module.domain
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

**DNS:** The zone is read on demand. In the interface, individual records can be
added and deleted. "Replace zone" only appears once the zone has been read: the
dialog is prefilled with its state and asks for confirmation before sending,
because a full replacement deletes every record missing from the form. Records
whose type the form cannot edit are kept unchanged; an empty form is rejected.
After a write the system detects deviations (DNS conflict) and surfaces them instead of
overwriting. MX/SRV records require a priority.

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
portfolio, balances and level, and allows customer assignment per subuser. For
each subuser you can enter the customer number at the provider; the monthly
vouchers built from the accounting journal in the reselling register then
carry the portal’s invoice numbers (“customer number-MM-YYYY”).

**Unclear commands:** If the provider’s answer stays incomplete, the command is set to “unclear” and is never repeated. The next sync run reads the domain’s state at the provider: registration, renewal mode, transfer lock and renewal are thus confirmed, recognised as failed or marked as a conflict. Transfers, DNS and high-risk actions have no status query — check them at the provider and resolve them manually with a reason in the command journal.

**Costs and balance:** In the organisation settings you set a cost threshold for renewals and a minimum balance. Domain management reports upcoming renewals above the threshold and provider accounts whose balance plus credit limit does not cover the renewals of the coming days — without coverage the provider deletes the domains on their failure date.

**Renewal mode:** You can choose the three modes the provider documents:
auto-renew, auto-expire and auto-delete. A previously stored value "Renew once" is
unknown after the update; the next sync reads the actual mode from the provider.

## Sync state of a domain

While a command to the provider is running, the domain shows **Pending**. If its outcome stays unclear or the provider differs after writing, it shows **Unclear** or **Conflict** and appears in the **sync issues** tile; a sync does not overwrite this while the command is open. After completion – automatic or manual – the domain shows **Stale** until the next sync reads it again.
