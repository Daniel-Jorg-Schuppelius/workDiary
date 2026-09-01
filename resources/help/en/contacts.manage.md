---
title: "Customers & suppliers"
topic: contacts.manage
version: 2
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
