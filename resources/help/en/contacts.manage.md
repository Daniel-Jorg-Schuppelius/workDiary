---
title: "Customers & suppliers"
topic: contacts.manage
version: 10
keywords:
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
