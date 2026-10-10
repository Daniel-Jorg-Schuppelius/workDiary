---
title: "Customers & suppliers"
topic: contacts.manage
version: 4
keywords:
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
3. Complete payment and billing data as well as external identifiers —
   they drive invoicing and the accounting handover.
4. Link projects, sites and agreements as they come into being.

![Customer list with numbers, contact data, hourly rates and project count](media/kunden/kundenliste.png)
*The customer list: master data, hourly rate and linked projects per business partner.*

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
