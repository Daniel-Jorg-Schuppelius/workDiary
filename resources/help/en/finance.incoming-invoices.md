---
title: "Incoming invoices"
topic: finance.incoming-invoices
version: 2
keywords:
    - supplier invoice
    - purchase invoice
    - inbound invoices
    - invoice mailbox
    - incoming invoices
    - receive XRechnung
    - ZUGFeRD
    - Factur-X
    - validate e-invoice
    - assign invoice
    - collective supplier
    - collective customer
    - unrecognised document
    - invoice approval
    - accounts payable
    - payment approval
    - EN 16931
    - handover to Lexware
    - posting category
audience: []
modules:
    - module.vertrieb
related:
    - invoices.manage
    - finance.datev-bookings
---

**Incoming invoices** (menu Billing → Incoming invoices) receives invoices,
assigns them to suppliers or customers and takes them through review and
payment approval — without touching the invoice sovereignty of your leading
accounting or invoicing software.

**Intake channels:** Invoices arrive via the invoice mailbox, file upload,
Peppol or the cloud storage. All channels go through the same processing:
duplicate check, security check, reading the e-invoice or recognising it from
PDF or image, validation and deviations. The unchanged original is stored as
a document of type invoice in the DMS.

**Invoice mailbox:** Under Administration → Email intake, a mailbox becomes
part of incoming invoices with the “Invoice mailbox” switch. Each email is
evaluated by invoice, not by attachment:

- An XRechnung (XML) is the original. A PDF of the same invoice sent along is
  attached as a companion file.
- Further attachments such as terms and conditions or delivery notes become
  companion files.
- Embedded logos and signature images are not processed.
- If an email contains no recognisable invoice, the attachment becomes an
  **unrecognised document**: the original is kept and you enter the values
  yourself.
- Emails without any invoice attachment (for example only a download link)
  go to the assignment inbox in Administration, marked as invoice mailbox.

**Recognition:** An e-invoice (XRechnung or ZUGFeRD/Factur-X) provides
binding values. For PDF and photo, the values are recognised and are a
suggestion that you check against the original. If an invoice in domestic
B2B trade is not an e-invoice, a notice appears: this is only permitted
during the transition period, until the end of 2026, or until the end of 2027
for small issuers. The notice blocks nothing; small-amount invoices up to
€250 are exempt.

**Direction:** If you are the buyer, it is an incoming document with a
supplier as counterparty. If you are the seller — for example with invoice
copies from a shop or till, or with credit notes in the self-billing
procedure —, it is an outgoing document with a customer as counterparty.
Outgoing documents never appear in payment proposals, payment runs or
retentions. If an invoice names a foreign buyer, the check reports “not
addressed to us”; a copy of one of your own invoices that is already recorded
is reported as well.

**Work list:** The tabs “To assign” and “To review” show all open documents,
“All” shows the selected period. The menu entry counts the documents to
assign. Accounting receives a notification in the morning as long as
something is left to assign.

**Assignment:** A document is assigned automatically only if exactly one
party matches an exact identifier of the document: VAT ID, tax number or
IBAN. If several match, the document stays to assign and the check names the
candidates. Your organisation’s own identifiers never count as a match. With
“Assign” you choose yourself:

- an existing party (suggestions are listed first),
- the collective supplier or collective customer,
- a new party, prefilled from the document data,
- “Not an invoice” — the document is rejected with a reason.

You can also correct the direction there. With “Remember sender” the system
assigns future emails from this sender to the same party, as long as the
document itself does not name another party. For unrecognised and recognised
documents, enter number, date and amounts with “Enter values”. Assign several
documents of the same party together in the list.

**Collective supplier and collective customer:** For one-off suppliers and
customers, each organisation has one collective contact. The name of the
real party stays on the document. A collective contact is never transferred
to an accounting system as a contact of its own, cannot be merged, receives
no portal access and no invoice of its own. For reverse charge (section 13b),
intra-community cases and third countries it is blocked — these need a real
company contact.

**IBAN check:** If the IBAN on the invoice differs from all stored bank
details of the supplier, the page points this out and payment requires a
confirmation. Invoices to the collective supplier always require this
confirmation. An invoice never changes master data.

**Duplicates:** Identical file content is captured only once per
organisation — across channels as well (an upload after a previous email
intake remains a duplicate).

**Validation and consistency:** Every e-invoice is validated against the XML
schema and, if set up, against the KoSIT rules (EN 16931); whether the checks
were available is shown. In addition, the deviation check warns visibly —
never silently — about an invoice number already recorded for the same
issuer, inconsistent totals (net + tax ≠ gross) and tax shown without the
issuer’s tax identifier.

**Review workflow:** A document is approved, put on query or rejected
(rejection only with a reason). Payment approval is only possible after
approval. Every decision is logged with person and time.

**Handover to accounting:** If Lexware Office or DATEV Unternehmen online is
connected and the handover is switched on there, a document is handed over as
soon as its counterparty is known. Approval remains the prerequisite for
payment, not for the handover. Beforehand the system checks: no unrecognised
document without values, not rejected, addressed to us, not a copy of one of
your own invoices, consistent totals and no collective contact for reverse
charge, intra-community cases or third countries.

- Lexware Office receives the voucher as “to be checked” with the original,
  for an XRechnung also with the PDF sent along. If a voucher with the same
  number already exists there for the same contact, it is only linked.
- Amounts are only sent with a posting category — on the supplier or
  customer, or as a default in the plugin settings —, in euros and with the
  tax rates 0, 5, 7, 16 or 19 %. Otherwise the voucher is sent without
  amounts and accounting adds them in Lexware.
- DATEV Unternehmen online receives the original as a document image.
- A voucher created there can no longer be deleted via the interface.
- Do not also send the same invoices to Lexware’s voucher email address:
  vouchers recognised there have no number at first and escape the duplicate
  check.

The status per target is shown on the detail page; the tabs “Handover open”
and “Handover failed” collect what is stuck. The system retries failed
handovers hourly up to five times; “Try again” starts them at any time. Without a
connected accounting system, “Hand over to accounting” records the handover
as evidence after approval; a second call changes nothing.

**XML download:** The invoice XML can be extracted from the original at any
time (for ZUGFeRD from the PDF attachment). Every download is logged with a
checksum as evidence.
