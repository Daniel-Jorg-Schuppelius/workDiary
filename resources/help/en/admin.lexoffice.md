---
title: "Lexoffice Conflicts"
topic: admin.lexoffice
version: 10
keywords:
    - Lexware Office
    - sync conflict
    - synchronisation conflict
    - data mismatch
    - resolve conflict
    - keep local values
    - accept remote values
    - data reconciliation
    - contact sync
    - voucher mirror
    - voucher sync
audience:
    - admin
    - buchhaltung
related:
    - admin.plugins
    - articles.lexoffice
    - invoices.manage
    - admin.integration-inbox
    - inventory.conflicts
---

Here you resolve synchronization conflicts with Lexoffice. A conflict
arises when a local record (WorkDiary) and the corresponding Lexoffice
contact diverge in one or more fields and the **Conflict strategy** in
the Lexoffice settings is set to **Manual review** (the default). The
conflicts are handled in the **Mapping Inbox**: opening this page takes
you there, filtered to the source **Lexoffice** and the case **Field
conflict**.

In the Mapping Inbox:

- For each conflict the differing fields are shown side by side as
  **Local** and **Remote**.
- Customers and suppliers are affected, i.e. the contacts from
  Lexoffice.
- The status filter also brings back conflicts that have already been
  resolved.

Resolution paths per conflict:

- **Adopt remote**: updates the local record with the Lexoffice values
  of the differing fields.
- **Keep local**: retains the local values; the differing Lexoffice
  values are not applied.
- **Dismiss**: closes the conflict without any change (e.g. for
  intentionally different data); it receives the status **Discarded**.

Risks: **Adopt remote** overwrites local values. Review the compared
data carefully before deciding. Note that for invoices the billing
authority rests with the external program – WorkDiary supplies data to
it.

The conflict strategy applies to contacts. Lexoffice leads the articles:
the article sync takes over every newer Lexoffice version, so there are no
article conflicts.

**Addresses with “Lexoffice wins”:** Lexoffice keeps addresses without an ID of
their own; WorkDiary matches them by type and order. A billing or delivery
address created in WorkDiary takes over the matching Lexoffice address on the
first sync instead of being created a second time: the primary billing address
takes the first one from Lexoffice. Afterwards only that one is primary; further
addresses in WorkDiary remain as additional ones.

**Tax type on transfer:** invoices and invoice drafts from the invoicing
handover carry the tax type of their tax case: service to an EU business as
“external services § 13b EU”, § 13b domestic as “construction services
§ 13b”, intra-Community supply, export delivery, service not taxable in
Germany as “service to third country”, small business as “tax-free” — with
0 % per position and the note as tax note. Lexoffice accepts these tax types
other than “tax-free” only with a contact created there. If a service not
taxable in Germany lies in the EU (for example a property in another EU
country), Lexoffice has no matching tax type: the transfer is blocked with
the reason. The voucher from times on the customer is a bookkeeping
voucher: per tax rate the net amount, the tax and the booking category of the
tax case (“Dienstleistungen”, “Fremdleistungen § 13b”, “Dienstleistungen an
Drittländer”, “Einnahmen als Kleinunternehmer”), with the voucher number
`ZE-<customer number>-<end of service>-<sequence>`. For domestic § 13b,
tax-exempt sales under § 4, several tax cases or a foreign currency it is
blocked with the reason; such times go through the invoicing handover.

Voucher mirror: the voucher sync mirrors the Lexoffice vouchers into
WorkDiary. Vouchers of Lexoffice contacts that are not yet linked to a
customer or supplier are mirrored too – initially without customer and
supplier; once the contact is linked, the next sync assigns them. A customer
is set only on sales documents and a supplier only on purchase documents,
even if a contact is linked as both. The line sync also stores the net
amount and, for a credit note, its reference to the invoice. You start the
run manually in the **Lexoffice settings** (Plugins → Lexoffice) with **Sync
vouchers now**: it fetches new and changed vouchers in the background, the
daily full sync stays in place; the button needs the **Sync vouchers with Lexoffice**
permission and stored credentials.

Permission: the Mapping Inbox is open to administrators and the
**Accounting** role.

**Data ownership:** If, according to data ownership, another program leads customers and suppliers, the contact sync applies no fields and creates no contacts; linking and the Lexoffice contact number remain. The withheld state waits in the integration inbox for **Take over once**.
