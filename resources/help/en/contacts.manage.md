---
title: "Customers & suppliers"
topic: contacts.manage
version: 20
keywords:
    - consumer
    - private customer
    - Delivery address
    - Preview as customer
    - Start password
    - Create access
    - customer master data
    - supplier master data
    - add customer
    - add supplier
    - vendor
    - debtor
    - creditor
    - debtor number
    - merge duplicates
    - import customers
    - address book
    - business partner
    - CRM
    - customer portal
    - portal access
audience: []
modules:
    - module.vertrieb
schema: process
related:
    - admin.number-formats
    - projects.manage
    - invoices.manage
    - admin.import
    - communication.notes
---

## Purpose and background

Customers and suppliers are WorkDiary's central master data: projects,
orders, invoices, communication, travel and reports all hang off them.
Clean master data decides whether later processes — from time booking
to the DATEV handover — work without rework.

## Requirements

- The right to manage customers or suppliers (usually administration
  or sales).
- For imports instead of manual entry: the CSV import wizard.
- External identifiers (e.g. debtor number, identifiers from billing
  integrations) if documents are to be handed over.

## Recommended workflow

1. **Search before creating:** check whether the business partner
   already exists — this prevents duplicates. Existing duplicates can
   be merged; the history moves along.
2. Create the contact with name, address and contact persons.
   If an accounting program assigns customer numbers according to
   **Number formats**, the field is locked: the customer is transferred to
   the program on creation and receives its number.
   Enter the contact person with salutation, title, first name, additional
   first names and last name; every row of the contact person list has the
   same fields. The accounting programs receive them separately.
3. Complete payment and billing data as well as external identifiers —
   they drive invoicing and the accounting handover.
4. Link projects, sites and agreements as they come into being.
   A new site takes address, country and coordinates of the chosen customer as a proposal.

![Customer list with numbers, contact data, hourly rates and project count](media/kunden/kundenliste.png)
*The customer list: master data, hourly rate and linked projects per business partner.*

**Authorities and levy recipients:** If you keep the tax office, health
insurance funds, the employers' liability insurance association or the
municipality as suppliers so that accounting can book taxes and
contributions, tick **Hide in reports** there. The vouchers are kept; the
supplier reports leave these bodies out. For customers, the same checkbox
applies to the customer and project reports.

**Communication:** Record calls, e-mails and commitments as a communication
note on the customer or supplier. The notes appear on the detail page and in
the central notes list; a data protection access report for a supplier lists
them with count and period.

**Portal accesses:** In the **Portal accesses** section of the customer record
you invite contacts to the customer portal with **Invite access**; the contact
sets their own password via the link in the invitation. While the invitation
is open or has expired, **Re-send invitation** is available. For active
accesses, **Reset access** resets the access after a confirmation prompt: the
previous password stops working immediately, all sessions are ended and the
contact receives a new invitation; two-factor methods that have been set up
remain in place. If the contact has merely forgotten their password, this is
not necessary – they reset it themselves on the portal sign-in page via
**Forgot password?**. **Deactivate** signs the access out immediately and
blocks sign-in, **Reactivate** lifts this again. Which areas an access can see
is determined by the customer's portal configuration.

If a contact has lost all two-factor methods and recovery codes, **Reset second factor** removes all methods after a password confirmation and a confirmation prompt and ends all sessions. The contact receives an email about it and then signs in with their password; if your organization requires two-factor authentication, they set it up again at that point. Verify their identity beforehand, for example by calling them back.

**Create access** creates an access without an invitation email, for example on the phone or in a meeting: after **Name** and **Email**, the customer record shows a random **start password** once. Hand it over in person or by phone, never by email; the portal does not send it and stores it nowhere in plain text. Until the first login the access shows **Start password pending**; at the first login the contact must set their own password. If they lost the start password, **Generate start password** creates a new one (the previous password stops working immediately, all sessions end). Both require a password confirmation from you and are logged.

**View portal** opens the customer portal in a new tab the way this contact sees it – with their areas, documents and invoices – and helps with questions like "Where do I find …?". The preview is a view only: actions are locked, security and password pages are not reachable, and it does not count as a login of the contact. It ends with **End preview** in the yellow bar or in the customer record, after 60 minutes at the latest, and is logged with start and end. It requires the permission **View the customer portal as a customer**; without an access there is no preview – create one first.

**Portal visibility:** Next to the areas you choose the **visible period of times** here – **organisation default** (setting **Customer portal** in the organisation settings, three calendar years including the current one by default), a fixed number of years or **unlimited**. This is a view limit in the portal, not a deletion. The **Insights** release shows the customer key figures and charts – only from areas that are released as well (times in the configured detail level, invoices, billing account, tickets); like every new release it starts “off”.

**Removing an access:** An access that has **never signed in** (invitation pending or expired, start password outstanding) is deleted permanently with **Delete access** – there is no evidence attached to it. If the contact has signed in before, the record stays: deactivate the access and choose **Remove access**. Name, address and credentials are dropped, the e-mail address becomes available for a new invitation, and photo confirmations, queries and requests remain as evidence (“Former portal access”). Both require a password confirmation and are logged on the customer; removed accesses only appear in the summary line below the list. The retention check (area **Portal accesses**) proposes deactivated accesses for removal after one year.

## E-invoice

In the customer dialog the e-invoice fields are in the section **Billing &
display**:

- **Default e-invoice format**: preset for new invoices and the file import of
  this customer, such as **XRechnung (XML)** or **ZUGFeRD (hybrid PDF)**; **No
  default (PDF)** keeps the PDF.
- **Routing ID / buyer reference (BT-10)**: mandatory in the XRechnung – the
  routing ID (Leitweg-ID) for public authorities, otherwise a reference set by
  the customer. If the value looks like a routing ID or the customer is a
  public authority, the dialog stores it normalised (capital letters, no
  spaces) and checks structure and check digits against the KoSIT format
  specification. An invalid routing ID is stored anyway; the dialog warns and
  names the reason, such as a wrong check digit.
- **Peppol participant ID**: recipient identifier for sending via Peppol in the
  form scheme:identifier, for example with the VAT ID or the routing ID; empty
  means no Peppol dispatch to this customer. With scheme 0204 a valid routing ID
  must follow, otherwise the dialog rejects the identifier – nothing can be
  delivered to a wrong address.
- **E-invoice recipient profile**: **Public authority** sends only the XML by
  email, requires the routing ID, embeds the documents and attaches the
  activity report automatically; **Standard** applies to all other customers,
  **Organisation default** uses the organisation's setting.
- **Attach activity report**: **By recipient profile**, **Always** or
  **Never**; the report is generated from the billed times when the invoice is
  issued.
- **Largest e-invoice with documents (MB)**: upper limit for the e-invoice
  including embedded documents; if empty, the profile's limit applies (standard
  15 MB, public authority 10 MB).
- **Dispatch route with external invoicing authority**: if an invoicing system
  controls number and content of the invoice, either the invoicing system
  delivers it or WorkDiary sends the invoice with the supporting documents – in
  that case switch off automatic dispatch in the invoicing system. You only see
  this field with the right **Manage finance configuration**.

In the section **Portal & miscellaneous**, next to the VAT ID, there is the
**Register number (commercial register)**, for example “Amtsgericht
Musterstadt HRB 12345”. The e-invoice states it as the customer's legal
registration identifier (BT-47). If the customer owes the tax (§ 13b UStG) and
has no VAT ID, the register number is enough for the e-invoice.

**Delivery address:** If goods go to a different address than the invoice,
enter it in the collapsible section **Delivery address** (street, address
supplement, postal code, city, country). If the section stays empty, the
billing address is the delivery address; clearing all fields removes an
existing delivery address. Lists, document header and accounting system keep
the billing address. For an intra-community supply the e-invoice names the
delivery address with the delivery country; an issued invoice keeps the one
valid at issue, cancellation and corrective invoice take the one of the
original. The detail page shows it under **Contact**, it is a field of its own
when merging, and the data subject access report, CSV import and export include
it (columns `shipping_street`, `shipping_supplement`, `shipping_zip`,
`shipping_city`, `shipping_country`; headers such as “Lieferstraße”,
“Liefer-PLZ”, “Lieferort”, “Lieferland”). The customer API and the MCP tool
`create_customer` accept the register number and the delivery address; an
update via the API changes only the delivery address fields that are sent.

How documents get into the e-invoice and what applies to public-authority
customers is described in the help topic **Invoices & documents**.

## Consumers and place of supply

If the customer is a private individual or another non-business recipient, tick
**Consumer (not a business)** in the **Portal & miscellaneous** section.
Together with the place-of-supply type of the service it determines the tax
case (§ 3a UStG): for consumers in a non-EU country, catalogue services –
consulting, data processing, rights, advertising, hire of movable goods and
similar – are not taxable in Germany; other services carry German VAT.
Unticked, the customer counts as a business as before; existing customers do
not change. A consumer is never liable for the tax itself: the boxes
**Consumer** and **Recipient is liable for VAT** cannot both be ticked.

The detail page shows the **Recipient** (consumer or business); when merging it
is a separate field. Subject access, CSV import and export (column
`is_consumer`, a header such as “Verbraucher”), the customer API and the MCP
tool `create_customer` include it; an API update without the field leaves it
unchanged. An issued invoice keeps the state at issue, cancellation and
corrective invoice keep that of the original. Where the place-of-supply type is
maintained is described in the help topic **Invoices & documents**.

## Practical example

An IT service provider creates "Müller GmbH", storing the billing
address, payment terms and the debtor number from the tax office.
When the first DATEV batch is created later, not a single document is
blocked by missing master data.

## Common mistakes

- **Creating duplicates** because nobody searched first — reports and
  history splinter.
- **Deleting historical relations:** deactivate or archive contacts
  no longer in use; documents and times stay traceable.
- **Changing billing data "on the side":** changes affect future
  processes; documents already created deliberately keep their
  documented state.

## Effects and next steps

Master data changes only apply going forward — completed handovers
remain unchanged. Next: create projects for the customer, check the
billing data for invoices and use the CSV import for larger sets.

## Supplier: tax data and self-billing

The supplier carries the **VAT ID** and **tax number**. If your organisation bills a supplier’s services itself, enter the date of the **self-billing agreement**; only then can self-billed credit notes be issued to the supplier. The supplier page shows the agreement and the latest credit notes.

## Invoices & vouchers in the file

Besides the invoices created in WorkDiary, the **Invoices & vouchers** card
shows the documents of connected accounting programs for the period in the
header: Lexoffice, sevDesk, easybill, orgaMAX, Invoice Ninja, Akaunting,
JTL-Wawi and InvoicePlane for customers and suppliers. A program
appears as long as its document import is set up for your organisation; the
“Source” column names it. Documents are assigned during the import through the
contact link, the customer number or the supplier name; if the link is
missing, the empty card says so. Where the program provides a PDF (sevDesk
invoices and credit notes, easybill, orgaMAX invoices), **Original PDF** loads
it directly. Incoming
documents of these programs can also be allocated as material costs of the
customer and in the purchasing of subscriptions and licences.

## Transferring suppliers to accounting

If WorkDiary leads the master data, suppliers are transferred to the
accounting program just like customers: Lexoffice, sevDesk, easybill and
orgaMAX. They are transferred when created, if the program assigns the
supplier number according to **Number formats**, and after changes to a
supplier that is already linked. easybill and orgaMAX can assign the supplier
number; sevDesk creates a contact in the “Supplier” category and keeps no
supplier number of its own, so the number from WorkDiary remains. orgaMAX only
accepts suppliers if the “Suppliers” capability is active in the orgaMAX
administration and “WorkDiary leads” is set; otherwise orgaMAX leads the
suppliers and WorkDiary only links them.

This does not reach suppliers from before the connection. **Transfer to
accounting** on the supplier record transfers one of them to the first active
program that accepts suppliers; operations can transfer all of them at once
with `php artisan accounting:push-contacts <program> --suppliers`. A supplier
that is already linked is updated, not created twice; archived suppliers and
the collective supplier are left out. If accounting leads the master data, the
button is not shown and the run transfers nothing.

## Master data from the accounting program

If master data authority is set to “Accounting leads”, WorkDiary transfers no
contacts. Instead, customers and suppliers come from the program: Lexoffice
and orgaMAX through their own contact sync, sevDesk and easybill every hour by
these rules:

- A contact that is already linked takes over the program’s data (name, VAT
  ID, tax number, address, email and phone; for easybill also the contact
  person).
- A safe match, for example by VAT ID, is linked.
- Anything unclear appears as a case in the **Mapping Inbox**; nothing is
  created.

sevDesk provides the role through the contact category: “Customer” and
“Supplier” are imported, partners, prospects and contact persons of a company
are not. The address is the sevDesk address of the category WorkDiary uses
when transferring, otherwise the only address; for email, phone, mobile, fax
and website the one marked as main applies, otherwise the only one. If there
are several without these markers, the value in WorkDiary stays unchanged, as
does the country. easybill distinguishes by customer and supplier number; a
contact with both numbers is customer and supplier. Archived easybill contacts
are left out.

## Customer and supplier cockpit

The record brings together what other modules know about the contact: for
customers open tickets, contracts with their next deadline, agreements,
warranty periods, guarantees, surveys with NPS, domains as well as
subscriptions and licences; for suppliers purchase orders, mandatory
credentials, contracts and deadlines. A section only appears if the module is
enabled, you have the right to see it and there are entries; the figures match
the respective list. Administrators see shop orders from Etsy and Billbee.
Like customers, suppliers have custom fields and documents.

For suppliers, **Catalogues** (name, number of items, last import; with the warehouse read right) and **Performance** are added: the scorecard of the last twelve months with overall score, on-time delivery, complaint rate, price development, ISMS quality, recourse behaviour and lead time — each row opens the scorecard for the same period. Leasing and financing contracts with the supplier as lessor appear in the record with the right to the leasing files. Customers additionally show booked and requested **Appointments** (Calendly, appointment booking) to everyone who may open the “Appointment requests” page.

**Accounting leads:** If, according to data ownership, the accounting software leads customer and supplier master data, the form shows a notice. You can still change everything, but the next synchronisation overwrites local changes — make lasting changes in the accounting program.
