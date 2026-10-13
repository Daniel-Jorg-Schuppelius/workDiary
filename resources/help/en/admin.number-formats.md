---
title: "Number Formats"
topic: admin.number-formats
version: 6
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
    - separator
    - billing route
    - quote number from the program
    - invoice number from the program
    - invoice to Lexoffice
    - invoice to sevDesk
    - invoice to easybill
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

## Saving and separators for all ranges

You save all changes on the page together with **Save** in the header.
Ranges you do not change stay on the default ("Default"). If an entry is
invalid, the message names the range and your entries are kept.

Above the table are the fields **Separator after prefix** and **Separator
after year** for all ranges. **Apply to all ranges** sets both separators in
every range that WorkDiary assigns and saves the page. An empty field means
no separator. Numbers already assigned stay unchanged.

## Who assigns customer and supplier numbers?

In the **Assigned by** column you choose for **Customer** and **Supplier**
whether WorkDiary assigns the number or a connected accounting program.
Active programs that can assign numbers are offered: Lexoffice (customer and
supplier), sevDesk and easybill (customer). orgaMAX does not assign customer numbers
for WorkDiary.

If a program assigns the numbers:

- A new customer first receives a draft number (`ENTWURF-…`). When created
  in the form, it is transferred to the program at once and receives its
  number. An hourly run catches up on customers from imports and on failed
  transfers.
- The **Customer number** field is locked for these customers.
- The **Next number** column shows the latest number taken over, how many
  contacts are still waiting for their number and, for sevDesk, the
  program's next number.
- If the program's number already belongs to another customer, the previous
  number stays and the **matching inbox** shows the case. Once you renumber
  the other customer, the next sync takes over the number.
- Existing customers keep their number.
- If the accounting program leads the master data, WorkDiary transfers
  nothing; the number arrives once the contact sync links the customer.

If WorkDiary assigns the numbers, every transfer checks whether the number
already belongs to another contact in the program and then stops with a
message instead of overwriting that contact.

## Invoice, credit note and cancellation

In the **Assigned by** column you also choose for **Invoice**, **Credit
note** and **Cancellation** who assigns the number: WorkDiary or a connected
program – Lexoffice and easybill for all three, sevDesk for invoice and cancellation, orgaMAX for the invoice. If a program assigns, a draft carries a draft number; **Issue**
transfers the document and adopts the program's number, credit note and
cancellation with a reference to the transferred invoice. Create down payment
and final invoices in the program then.

If the **billing route** in the organization settings is set to an
accounting program, that program keeps the invoices entirely; the **Assigned
by** column names it. If individual customers bill via another route, the
number of customers is shown below.

## Locked ranges

If a program assigns the numbers of a range, it also sets the format.
Prefix, separators, year, digits, annual reset and start are then locked;
the saved values apply again as soon as WorkDiary assigns. If a customer is
billed in WorkDiary despite an external billing route, invoice, credit note
and cancellation stay open because that customer's invoices receive local
numbers. The **Assigned by** selection stays
editable; the fields become available after saving.

## Quote, order confirmation and delivery note

For **Quote**, **Order confirmation** and **Delivery note** you choose
Lexoffice or easybill in the **Assigned by** column, for quote and order confirmation also orgaMAX. The number is then
created when the quote is sent, the order confirmation is confirmed and the
delivery note is delivered, as WorkDiary transfers the document to the
program.
