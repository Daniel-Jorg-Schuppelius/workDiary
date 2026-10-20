---
title: "Incoming invoices"
topic: finance.incoming-invoices
version: 3
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
    - duplicate receipt
    - duplicate invoice
    - invoice folder
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

**Invoice mailbox:** Under Administration → Data & interfaces → Email intake,
open the mailbox via “Edit” (or add a new one) and switch on “Invoice mailbox”
under “Usage” in the dialog. The mailbox list then shows the “Invoices”
badge. Each email is evaluated by invoice, not by attachment:

- An XRechnung (XML) is the original. A PDF of the same invoice sent along is
  attached as a companion file.
- Further attachments such as terms and conditions or delivery notes become
  companion files.
- Embedded logos and signature images are not processed.
- If an email contains no recognisable invoice, the attachment becomes an
  **unrecognised document**: the original is kept and you enter the values
  yourself.
- If the server lacked text recognition when the email arrived, the bulk
  action “Recognise again” in the list of documents to assign repeats the
  recognition of the selected unrecognised documents from the stored
  original. Entered values, transferred and rejected documents stay unchanged.
- If the XML and the visual PDF of the same invoice arrive in one email, the
  XML stays the original and the PDF becomes a companion file, even if the
  XML carries a longer number. If the XML names no seller, VAT ID or bank
  details, the PDF supplies these values; the detail page lists the added
  fields.
- “Check again” repeats the schema and KoSIT check of e-invoices on the
  stored original, for example if the validator was missing on receipt.
  Values, assignment and transfer stay unchanged.
- Emails without any invoice attachment (for example only a download link)
  go to the assignment inbox in Administration, marked as invoice mailbox.
- An email forwarded as an attachment is unpacked; the invoice inside counts
  like a direct attachment.

**Fetching and existing mails:** Mails are fetched from the day set under
“Fetch from”, by position — including mails someone has already read. Ideally
choose a dedicated folder into which a rule sorts the invoices (separate
subfolders with “/”, for example Inbox/Invoices). “Fetch older mails” in the
mailbox administration fetches older invoices; the same invoice is not created
twice. If an invoice email is in another mailbox, “Take over as incoming
invoice” in the assignment inbox takes over its attachments.

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

With “Also treat the other open documents the same way” you handle all open
documents of the same counterparty in one step, when assigning as well as when
rejecting. Under “Not an invoice” you decide what happens to future documents
from this counterparty: “file automatically as not an invoice” or “do not take
in at all (discard)”. Discarding removes the document, its file and
attachments; the audit entry with checksum, number, seller, sender and reason
remains. WorkDiary creates an inbox rule for this.

**Inbox rules** are automation rules for the trigger “Incoming invoice
received”. You see them via “Rules” in the page menu together with the sender
rules, switch them off and on and delete them; administrators edit conditions
and actions on the automation page. Conditions check, among others, seller
(`seller_name_key`, normalised), VAT ID (`seller_vat_id`), sender
(`sender_email`, `sender_domain`), subject (`mail_subject`), attachment names
(`attachment_names`), file name (`file_name`), country (`country`), gross
amount (`amount_gross`) and recognition (`recognition`); operators include
“is”, “contains”, “starts with”, “ends with” and “matches” (regular
expression). Actions: discard, file as not an invoice, assign to a party. The
first matching rule wins; rules apply on retrieval, on upload and on
re-recognition.

Rejected documents are listed in the “Rejected” tab, where you can delete them
permanently. After the configured period (default 30 days) retention proposes
their destruction; deletion happens only after approval.

**Recognition of PDFs and scans:** WorkDiary reads labels and values even when
they sit in separate columns or in a header row above the value row. If there
is no invoice date, “Place, date” in the letterhead or a bare “Date” counts; a
delivery date is only suggested and marked in the “Enter values” dialog.
Reminders, delivery notes, order confirmations, quotes and shipping labels are
recognised as such: the list shows “Looks like”, the assignment dialog
preselects “Not an invoice”, and reminders name the invoices concerned with a
link. Administrators measure recognition with
`incoming-invoices:measure-recognition` against the stored document texts.

**Document templates:** Via “Template” on the document page you draw
rectangles on the rendered document for invoice number, date, amounts, VAT ID
and seller; the recognised value appears immediately. The template is saved
with a fingerprint of the header and footer of the first page, so it matches
every further document of the same supplier, on request the open ones right
away. Every correction in “Enter values” learns as well: the entered value is
located on the document and remembered as a zone; after the second matching
hit a learned zone counts as confirmed and overrides the heuristics. Templates
are listed under “Rules” and can be deleted there. Zones exist for PDF
documents only.

**Correcting assignments and bulk action:** The bulk action requires a
choice; “New from document data (per counterparty)” creates one master record
per counterparty from the first document or uses an existing party with the
same VAT ID or name, the collective contact comes last and asks back with the
groups of the selection. Remove an assignment in the dialog via “Remove
assignment” or as a bulk action as long as the document has not been handed
over; moving is simply assigning again, also in the “To review” and “All”
tabs. Under “Rules”, “Parked at the collective contact” lists the documents at
the collective supplier or customer by counterparty; “Create and move” creates
or uses the party and moves the open documents. If an accounting program is
connected (Lexware Office, easybill, sevDesk, orgaMAX), the dialog searches it
by email and name and offers matches as “Adopt from accounting”: the master
record is created from the contact and linked together with the contact
number. sevDesk returns neither email nor address, orgaMAX suppliers no VAT ID.
Before “New from document data” the dialog warns about similar master records;
an existing party with the same VAT ID blocks the creation. If accounting
leads the master data, the dialog says that the new record is not transferred.

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

**Possible duplicate receipt:** If the same invoice arrives a second time as a
different file — for example as a PDF by email and as an XRechnung via Peppol
— incoming invoices recognise this by the invoice number together with the
issuer’s tax identifier or the assigned party. The later receipt is not handed
over to accounting until you confirm “Not a duplicate” or reject it. A number
alone is not enough: “1001” exists at many suppliers.

**Validation and consistency:** Every e-invoice is validated against the XML
schema and, if set up, against the KoSIT rules (EN 16931); whether the checks
were available is shown. In addition, the deviation check warns visibly —
never silently — about inconsistent totals (net + tax ≠ gross) and tax shown
without the issuer’s tax identifier.

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
- If the invoice number is missing, the handover searches Lexware by contact,
  voucher date and amount. A match is not linked automatically: you choose
  “Link with this voucher” or “Different voucher – create new”.
- DATEV cannot be searched for existing vouchers through the interface.
  Therefore do not also send the same invoices via the DATEV upload mail;
  protection against duplicates there rests solely with incoming invoices.
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
