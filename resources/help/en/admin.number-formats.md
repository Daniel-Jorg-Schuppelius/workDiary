---
title: "Number Formats"
topic: admin.number-formats
version: 2
keywords:
    - number range
    - invoice number
    - document number
    - customer number
    - article number
    - sequential number
    - numbering scheme
    - prefix
    - starting number
    - reset counter
    - numbers from accounting software
    - customer number from Lexoffice
    - draft number
audience:
    - admin
    - teamleitung
related:
    - invoices.manage
    - admin.classifications
    - glossary.core
---

Number formats define the sequential number ranges per organisation, for
example for service tickets, assets, articles, manufacturing orders, serial
numbers, purchase orders, customers, suppliers, invoices and credit notes. A
separate format is maintained for each scope.

For each format you configure the prefix and prefix separator, optionally
the inclusion of the year with a year separator, the padding width, the
starting value and whether the counter resets each year. A preview shows the
next number that would be generated.

The settings apply to the current organisation; if no organisation is
assigned yet, you are directed there first. Change the format or starting
value carefully, since numbers already issued are preserved.

## Who assigns customer and supplier numbers?

In the **Assigned by** column you choose for **Customer** and **Supplier**
whether WorkDiary assigns the number or a connected accounting program.
Active programs that can assign numbers are offered: Lexoffice (customer and
supplier), sevDesk and easybill (customer). orgaMAX does not assign numbers
for WorkDiary.

If a program assigns the numbers:

- A new customer first receives a draft number (`ENTWURF-…`). When created
  in the form, it is transferred to the program at once and receives its
  number. An hourly run catches up on customers from imports and on failed
  transfers.
- The **Customer number** field is locked for these customers.
- If the program's number already belongs to another customer, the previous
  number stays and the **matching inbox** shows the case. Once you renumber
  the other customer, the next sync takes over the number.
- Existing customers keep their number.
- If the accounting program leads the master data, WorkDiary transfers
  nothing; the number arrives once the contact sync links the customer.

If WorkDiary assigns the numbers, every transfer checks whether the number
already belongs to another contact in the program and then stops with a
message instead of overwriting that contact.

Invoice, credit note and cancellation numbers are set in the Lexoffice
plugin settings.
