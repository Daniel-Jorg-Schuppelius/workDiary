---
title: "Data ownership"
topic: admin.data-ownership
version: 3
keywords:
    - leading system
    - master system
    - system of record
    - single source of truth
    - data sovereignty
    - invoicing authority
    - billing system
    - Lexoffice
    - DATEV
    - handover record
    - avoid duplicate invoices
    - sync conflict
    - ownership conflict
    - take over once
audience:
    - admin
related:
    - admin.integration-inbox
    - admin.tenants
    - finance.transfers
---

This page defines, per organization, which system is the **leading
system** for which data domain – so that two systems never overwrite
the same data against each other.

**The matrix:** For each data area – tasks, tickets, inventory, calendar, documents, customers and suppliers – exactly **one leading system** applies: WorkDiary itself (the default) or an enabled integration. For each area, only integrations that can actually write it are offered. **Inventory** is not switched here but in the inventory system connection (e.g. JTL-Wawi → stock management); the matrix shows that setting. Dual ownership is structurally impossible.

**Effect of ownership:** If WorkDiary leads, imports from integrations remain allowed as usual. If an integration leads an area, only that integration may write there. If another integration tries to write – create or close a ticket from Zammad, take over a Calendly booking or a calendar appointment, book an opening stock from JTL-Wawi, import a task – it writes nothing. Instead, a “Data ownership” case appears in the **integration inbox**: **Take over once** writes the state exactly once, **Discard** drops it, and the same operation does not come back. Reading and display are always allowed.

**Changing the lead:** Every change is audited. Open conflicts stay in the inbox when you switch; the matrix shows their number per area. If the new lead allows the write path, the next sync closes the case itself; otherwise take it over or discard it there.

**Externally led ticket queues:** If Zammad imports service tickets into a queue, Zammad leads that queue. New tickets are only created there by the import; WorkDiary does not accept manual creation, mail booking or mail replies into this queue. You maintain status and customer communication in the leading system.

**Documents:** If an integration leads documents, only it creates DMS documents and new versions through the cloud document intake. Files from other cloud connections and documents retrieved from other programs (for example from easybill) wait as a “Data ownership” case; **Take over once** downloads the file again and creates it. Your own uploads, protocols and documents remain possible in WorkDiary – the DMS is also WorkDiary’s evidence store.

**Customers and suppliers:** This row is also the master data authority towards accounting. If you choose an accounting program (Lexoffice, sevDesk, easybill), it leads the master data: WorkDiary does not transfer contacts there, and the program’s contact synchronisation applies names, tax data and addresses. Other programs then do not change customers and suppliers, and the Toggl migration does not create any; their states wait in the integration inbox. “Accounting (any program that writes customers)” lets every connected program write. Local changes remain possible but are not transferred back, and the next synchronisation overwrites them. Customer numbers are still assigned by the number range, not by this row.

**Applied cases:** If the integration later wants to write a new state of the same operation – such as a changed task or a new file revision – the applied case reopens. Dismissed cases stay dismissed.

**Invoice sovereignty:** The same principle applies to invoicing:
exactly one program leads the invoices – WorkDiary, Lexoffice or
DATEV. You configure the billing route as a **default per
organization** and can override it **per customer**. The cascade is:
customer setting before organization default; without either,
WorkDiary invoices locally.

**Consequences of external sovereignty:** If an external program
leads a customer's invoicing, **local invoice creation is locked for
that customer**. Billable times and materials are handed over to the
leading program as a **billing transfer** instead: transfers start as
drafts, are confirmed, and only upon the actual handover are the
source items consumed as billed – so nothing can be invoiced twice.
Binding invoice number assignment remains entirely with the leading
program.

**Switching in production:** Changing the billing route only affects
future processes; documents already created remain unchanged. Before
switching, clarify which open items should still be completed via the
old route.

**Recommendation:** Keep the matrix deliberately lean – hand
ownership to an integration only where the external system truly is
the authoritative data source.
