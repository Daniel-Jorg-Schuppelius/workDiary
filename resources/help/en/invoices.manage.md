---
title: "Invoices & documents"
topic: invoices.manage
version: 22
keywords:
    - place of supply
    - catalogue service
    - immovable property
    - create invoice
    - sales invoice
    - billing
    - cancel invoice
    - credit note
    - e-invoice
    - XRechnung
    - Peppol
    - payment link
    - pay online
    - copper surcharge
    - Stripe
    - Mollie
    - SumUp
    - related documents
    - document chain
    - transfer invoice to Lexoffice
    - invoice inquiry
    - activity report
    - supporting documents
    - public authority
    - routing ID
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - contacts.manage
    - projects.manage
    - finance.datev-bookings
    - finance.transfers
    - travel-expenses.manage
---

## Purpose and background

The invoice overview manages local invoices and connected documents.
Which path leads depends on the organisation and the billing
integration in use: per period either WorkDiary issues the invoices
or exactly one external system — never both at once.

## Requirements

- Checked master data: customer, recipient address, tax details.
- **Service period and project link** of the positions to bill.
- The right to create invoices; for dunning runs the corresponding
  finance role.

## Recommended workflow

1. Choose customer and period — the creation dialog shows a
   **preview** of the resulting positions (count, duration in clock
   and decimal format, amount, late-entry warning).
2. Exclude individual time entries by checkbox if needed — they stay
   open and appear in the next run.
3. Check and complete the draft; per position the **source time
   entries** can be expanded (1.50 h = 1:30 h). For an article with
   copper weight, the **copper surcharge** checkbox in the line dialog adds
   the surcharge at the current DEL daily price as a separate line.
4. Issue or send — PDF, dispatch and external synchronisation are
   outputs of the same documented state.
5. On late payment use the **dunning run**: level 1 produces a payment
   reminder as its own dunning letter PDF with claims overview,
   optional fee and payment deadline; the e-mail contains the letter
   and the original invoice. No new document is created.

**E-invoice.** The XRechnung is created in UBL syntax; if a recipient
requires CII, choose the delivery format “XRechnung (XML, CII syntax)” on the
customer or when sending. Peppol always uses UBL. Without a VAT ID — for
example as a small business under § 19 UStG — the tax number in the e-invoice
master data is enough: it is also entered as the seller identifier that the
recipient’s validation requires.

## Practical example

At month's end accounting picks "Müller GmbH" and the previous month:
the preview shows 14 positions and warns about two late time entries.
One disputed entry is excluded and automatically moves to the next
run — the invoice goes out without a debate.

## Common mistakes

- **Silently changing sent or handed-over documents:** issued, booked
  or externally handed-over documents are immutable — mistakes go
  through the cancellation or correction process.
- **Overwriting document numbers or amounts** instead of correcting —
  this destroys traceability.
- **Double invoicing sovereignty:** if an external system runs the
  billing, local invoices deliberately do not exist in parallel.

## Effects and next steps

Issued invoices flow into open items, dunning and the accounting
handover. Next: check payment runs and allocation, and create the
DATEV batch for the tax office.

## Free invoice without times

In the create dialog, **“Compose line items yourself”** stands on equal footing
with taking over times or material usage. The draft only needs the customer
(optionally project, end customer and payment terms) and starts empty: it can
be saved and completed later, but cannot be issued or sent while it has no line
item — this applies to issuing, e-mail, Peppol and the Lexoffice handover
alike. A double click on “Create draft” does not create a second invoice.

**Articles, material and services.** A line item is an article (with an
optional variant), material or free text — such as “assembly, flat rate” or a
custom-made product without a manufacturing order. Description, article
number, unit and price are frozen as document values; later changes in the
article master do not alter the document. A missing price must be entered
deliberately (0.00 is allowed as a free item); an article price in a foreign
currency is never converted silently. Article and free-text line items post
**no stock**; deliveries run via inventory/delivery.

**Invoicing manufacturing deliveries.** With the inventory module active,
“Take over delivery” imports completed deliveries of the customer with a local
invoicing target — each entirely as one line item with a source-bound quantity
and the sales price of the delivery (not the production cost). A delivery can
only sit in one draft at a time; issuing marks it invoiced, removing the line
item, discarding the draft or a full cancellation release it again, and the
origin stays visible on the document. Partial credit notes release nothing;
stock stays untouched by all invoice operations.

## Flat rates from times

If an activity type is linked to a service with the unit "flat" or "Pauschale" —
for example an article in the project's billing rule or as the organisation's
default service —, the invoice run, the transfer and the Lexoffice time voucher
bill **one line with quantity 1 per order** at the price of that service, however
many times were booked. Times without an order give one flat rate per project and
activity type. The times stay attached to the line as proof and count as billed.
Rounding increments, travel minutes from the customer terms and the top-up to
agreed hours do not apply to flat rates. If the service has no price, the flat
rate shows 0.00 in the preview. "flat", "Pauschale" and "pauschal" count as a flat
rate, as do the counterparts in the other languages such as "forfait". Any other
unit — empty, "hour", "day", "pcs", "km" or free text — keeps billing by duration.

## Online payment

If a payment provider (Stripe, Mollie or SumUp) is set up as a plugin,
issued invoices carry a payment link with a QR code; the same address
appears in the email via the placeholder {{payment_link}} and in the
customer portal. The link always goes through workDiary: the provider's
payment page is created only when it is opened, for the amount open at
that moment, so a printed code stays correct after partial payments.

Once the provider confirms the payment, the invoice counts as paid or
partially paid — just like with an allocated bank transaction; a refund
reverses this. With local accounting, the payment appears in the posting
inbox (cash in transit against receivable, the provider's fee as bank and
payment charges); you book the provider's later payout against the
cash-in-transit account. An online prepayment from rental is booked on the
payment date as an advance payment received, with VAT; the invoice that
credits it releases the advance payment and shows only the open remainder
as receivable. You can switch off the link on invoices and emails in the
organization settings under “Online payment”; it remains in the customer
portal.

## Cancelling and billing again

A draft is cancelled directly, an issued invoice through a cancellation
document with its own number. Both ways release everything the invoice billed,
just like cancelling a handover: booked times, material, travel, expenses,
rental items, deliveries and the top-up to the project target. An online
prepayment from rentals returns to its booking and is credited on the next
invoice. The lines of the cancelled invoice stay unchanged; the next invoice
for the period contains the hours and the top-up again. A paid invoice is
corrected by a corrective invoice. It releases nothing, because it may credit
only part of the invoice.

An issued invoice that only an online prepayment partly covers is also
cancelled through a cancellation document: the prepayment returns to its
booking and is credited on the next invoice. If another payment partly covers
the invoice – a bank transaction in payment reconciliation (including an
accepted early-payment discount), a cash payment, an online payment of the
invoice or a settlement of the open item –, cancellation stays locked and the
invoice states why. Undo that payment first; once the invoice is paid in full,
correct it with a corrective invoice.

With local accounting, the cancellation document settles exactly the open item
of the cancelled invoice, after a prepayment the rest after the advance; other
open items of the customer stay untouched. The advance is open again
afterwards and is resolved with the next invoice. Post the invoice before its
cancellation document, therefore; otherwise the cancellation document waits in
the posting inbox with a notice. A cancelled invoice with a cancellation
document also appears there for posting, because both documents belong in the
journal. The cancellation document does not count as a payment, not even in
the cash-basis reports (cash-basis income statement, VAT on payments
received).

## Related documents

At the bottom of the page, **Related documents** lists every document
connected to this one: the quote and its versions, invoices, down payments and
the final invoice, credit notes and cancellations, delivery notes,
manufacturing orders and handovers. If an accounting program has taken over a
document, its counterpart appears there with the program's number. Links the
program reports itself, such as a down payment created in Lexoffice, appear as
well. The **Relation** column says what the document is to this one; "via …"
names the document through which the connection exists.

## Number from the accounting program

If an accounting program assigns the invoice numbers (**Number ranges**
page), the draft carries a draft number and the invoice shows a notice.
**Issue** – also sending a draft by e-mail – transfers the invoice to the
program and adopts its number before the PDF and subject are created.
Lexoffice and easybill take the order confirmation or the quote as predecessor; orgaMAX creates the invoice via an order and locks it. Credit note and cancellation go over with a reference to the
transferred invoice; for sevDesk and easybill the invoice must be in the program for that, orgaMAX takes neither. If the program refuses, the document remains a draft and
the message names the reason. In this case, create down payment and final
invoices in the program.

## Inquiries about invoices

When a customer replies to an invoice by e-mail, WorkDiary records an
**invoice inquiry** while polling the mailbox: in the invoice history, as a
communication note on the customer and in the **Inquiries** section of the
invoice page. It recognises the reply to the invoice e-mail sent by
WorkDiary as well as an invoice or document number in the subject or text –
also for documents issued by Lexoffice, sevDesk, easybill or another
connected program. For a number, the sender must belong to the case
(customer, contact person, portal account or recipient of the invoice
e-mail); otherwise the e-mail ends up flagged in the assignment inbox, as it
does with several numbers. Each message is recorded only once, out-of-office
replies never.

The **Invoice inquiries** work list (button in the document flow) shows open
and answered inquiries of all invoices and documents; **Mark as answered**
closes an inquiry, **Reopen** brings it back. Both appear in the invoice
history.

## Supporting documents and activity report

Timesheets and activity reports travel embedded in the e-invoice – in the
XRechnung, in the ZUGFeRD PDF and over Peppol. Public authorities and the tax
office require this: a separate email attachment or a link is not part of the
invoice. The card **Supporting documents** on the invoice page offers three
ways while the invoice is a draft:

- **Create activity report** builds a PDF from the invoiced times: per item the
  date, person, time, duration and activity, plus the approvals of signed
  timesheets. If you like, the signed timesheets and a CSV table are added
  (organisation, section **E-invoice: documents and recipients**).
- **Add document** takes your own file: PDF, PNG, JPEG, CSV, XLSX or ODS without
  password protection. Invoice recipients do not accept other formats.
- **Add link** is meant for portals only, such as the large attachments of
  OZG-RE. For tax purposes a link does not replace an embedded attachment.

WorkDiary names the files itself (`nachweis-1-…`) so that they are unique.
**Issue** fixes the documents; a generated report is created again with the
final invoice number. After that, documents can neither be changed nor
removed. Sending by PDF puts the documents as separate files into the same email.

The e-invoice states the time of supply in its structured part: the date of
supply or the period of the items, otherwise the invoice date. The description
of services stays in the items; the report adds to it.

### Public authority customers

In the customer record, set the **E-invoice recipient profile** to “Public
authority”. Then:

- The routing ID (buyer reference) is required. If its structure or check
  digits are wrong, the check before sending warns; this also applies to
  customers addressed via Peppol with scheme 0204.
- Email carries only the XRechnung, no PDF next to it; the send dialog only
  offers these formats. Electronic sending issues a draft first so that the XML
  carries the fixed documents.
- The activity report is attached automatically on issue when the invoice
  carries times.
- The e-invoice with documents may be at most 10 MB; the e-invoice check reports
  when it gets larger. The limit can be changed per customer, as can whether a
  report is always or never attached.

If an accounting program assigns the number, the documents stay in WorkDiary:
WorkDiary does not transfer them to Lexoffice, sevDesk, easybill or orgaMAX.
Whoever sends the e-invoice from the program attaches them there by hand; the
integration inbox reminds you. The billing transfer offers **Activity report**
for download.

## Releasing retentions

Retentions are listed on the invoice page in the **Retentions** card with
kind, amount, release date and status. On an issued invoice, the lock icon
opens the **Release** dialog; a note records the reason. Once released, the
amount is a regular open item, counts towards the amount payable again and is
included in dunning. A retention replaced by a guarantee has already been paid
out: releasing it only ends the security purpose, no new receivable is
created, and the guarantee register reports the deed as due for return.
Releasing requires the billing permission.

## Approval and tax case of the draft

With **mandatory approval**, the approval applies to the reviewed state: if you
change line items, conditions, customer, dates or the invoice text afterwards, the
approval is revoked and must be given again — not by the person who created the
invoice. The log records the revocation.

Every draft takes its **tax case** (reverse charge, export, tax-exempt) from the
customer data, including the free draft. If the customer data change afterwards —
for example through the attribute "Recipient owes the VAT" or a new VAT ID —, the
next edit of the draft applies the new case. If you issue a draft with an outdated
tax case, workDiary adjusts it and stops the issue: check the totals and issue
again.

With **reverse charge**, the e-invoice needs the customer's VAT ID (rule
BR-AE-02); for domestic reverse charge under § 13b UStG the register number will
do instead. The check before sending reports when the required detail is missing. The register number appears
in the e-invoice as the buyer's legal registration identifier (BT-47). All line items then appear with 0 % and the note "Reverse charge"
in the XRechnung.

The **notice text** follows the tax case: if the automatic notice of the old case
(for example on reverse charge or export) is still unchanged in the invoice text,
the notice of the new case replaces it, or it is removed. If you edited the notice
by hand, your text stays — workDiary tells you, and you check the notice yourself.

## Place of supply of services

Besides the customer, the **place-of-supply type** of the service determines the
tax case (§ 3a UStG). It is set where the service is defined: on the project’s
**billing rule**, otherwise on its **article** or on the line’s service article,
otherwise in the organisation default (organisation settings, tab
**Invoicing**). Out of the box everything is **General** – without maintenance
no tax case changes.

- **General:** as before; German VAT for consumers in a non-EU country
  (§ 3a (1) UStG), “not taxable” for businesses in a non-EU country.
- **Catalogue service** (§ 3a (4) UStG), e.g. consulting, data processing,
  rights, advertising, hire of movable goods: “not taxable” (O) with its own
  note for consumers in a non-EU country, otherwise as “General”.
- **Service connected with immovable property** (§ 3a (3) no. 1 UStG), e.g.
  construction, fit-out and cleaning work on buildings: if the property is in
  Germany, the invoice carries German VAT – also for customers in other EU
  countries and outside the EU; if the customer is liable for the tax under
  § 13b UStG, that still applies. If the property is abroad, the service is “not
  taxable” (O). The property is located at the site of the order; if none is
  stated, Germany applies.

Goods (material invoices) are not affected. For invoices from times, the rule of
the billed times counts; for free lines, the service article. Lines without an
article, travel charges and lines with their own tax category follow the
invoice. If the lines result in different tax cases, the draft says so, the
invoice cannot be issued and the check before sending rejects it (with “not
taxable” under rule BR-O-11): split the invoice, one per tax case. Cancellation
and corrective invoice take over the tax case of the original.

## Exemption reasons in the e-invoice

Every VAT category without tax carries its exemption reason in the XRechnung and the
ZUGFeRD PDF, per VAT breakdown on mixed invoices: reverse charge (AE), intra-Community
supply (K), export (G) and "not subject to VAT" (O) with text and code; category Z has
no reason. "Exempt" (E) names § 19 UStG only for small businesses. For other
exemptions, create a rule with the rate type "Exempt" in the **tax rule matrix** and
enter the exemption provision as its document notice text, for example "Tax-exempt
medical treatment (§ 4 no. 14 UStG)"; without a rule only "Tax-exempt supply" appears
and the check before sending warns.

**Non-EU countries:** goods for customers outside the EU are exports (G) with
the reason "Tax-exempt export supply". Services to businesses outside the EU are
not subject to German VAT (§ 3a (2) UStG) and appear as "not subject to VAT" (O)
in the e-invoice, the invoice PDF and the accounting: without VAT IDs, with the
organisation's tax number and not next to other categories. Without a tax number
the check before sending stops. Invoices issued earlier with category G are
reprinted unchanged.

The check before sending also reports: a missing customer VAT ID for intra-Community
supplies, a missing organisation VAT ID for supplies to other EU countries and
exports, "not subject to VAT" next to other categories, and an item without tax in
its category but with a tax rate above 0 %. With "not subject to VAT" the e-invoice
carries no VAT IDs; the tax number is enough. For intra-Community supplies the
e-invoice states the customer's different delivery address (customer dialog,
section **Delivery address**), otherwise the customer's address, as the delivery
address with the delivery country (rule BR-IC-12); the XRechnung needs postcode
and city there, otherwise the check before sending stops. The delivery address
is frozen at issue; cancellation and corrective invoice take the one of the
original. The organisation's register entry (**Branding**, field **Trade
register**) appears in the e-invoice as the seller's legal registration
identifier (BT-30), also frozen. Items "not subject to VAT" appear in
the e-invoice without a tax rate.

## Exemption note in the invoice PDF

Items without tax also need a note on the invoice itself (§ 14 (4) no. 8 and
§ 14a UStG). Below the tax lines, the PDF prints the same text per tax category as
the exemption reason of the e-invoice, for example "Tax-exempt intra-Community
supply" for K or the reverse charge wording for a single item with AE. If the
invoice text already contains the note — or the automatic note of this category
from the tax case — the line is left out. Reverse charge for the whole document
and § 19 UStG keep their own line as before. Invoices issued before this
extension are reprinted unchanged. Credit notes and cancellation invoices take
over the tax category of each item of the original.

If you choose a category without tax (AE, Z, E, G, K, O) on an item or in a billing
schedule, the form sets the tax rate to 0 %; 0 % is saved in any case.

## Recipient and VAT ID on the invoice

An issued invoice prints the recipient as it stood at issue: the customer's name,
company, address and VAT ID are frozen when the invoice is issued. If you change
the customer data afterwards, reprints, the PDF attachment, XRechnung, ZUGFeRD and
the GAEB export stay unchanged; only new invoices take over the new address. A
draft always shows the current customer data. Cancellation documents and
corrective invoices freeze the recipient when they are issued themselves. The
electronic delivery address (Peppol ID or e-mail) and dunning letters, by
contrast, follow the current customer data because they are delivered today.

Together with the recipient, issuing freezes the **organisation's details**:
company name, address, tax number, VAT ID, contact details, bank details (bank
block and payment QR code), the small business scheme and the **document
language**. Reprints, XRechnung and ZUGFeRD of an issued invoice stay as they
were issued — also after a change of bank, a new address or another document
language of the customer; drafts and new invoices use today's data. The texts of
the e-invoice are in the document language, not in the language of your user
interface. Dunning letters show today's bank details.

If the customer no longer has a VAT ID on file, cancellation and corrective
invoices for reverse charge or intra-Community supplies take over the VAT ID of
the original invoice, as well as a missing register number; the check before
sending no longer stops.

If a VAT ID is stored for the customer, it appears in the invoice header ("Your
VAT ID"). For intra-Community supplies and reverse charge it is mandatory
(§ 14a UStG). Invoices issued before this extension are reprinted without the
line.

Issuing also fixes the disclosure of labour costs under § 35a EStG: a later change
to the organisation rule or to the type of customer no longer changes an issued
invoice.
