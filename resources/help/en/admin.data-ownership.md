---
title: "Data ownership"
topic: admin.data-ownership
version: 1
audience:
    - admin
related:
    - admin.tenants
    - finance.transfers
---

This page defines, per organization, which system is the **leading
system** for which data domain – so that two systems never overwrite
the same data against each other.

**The matrix:** For each data domain (e.g. tasks, tickets, inventory,
calendar, documents, customers) exactly **one leading system**
applies: either WorkDiary itself ("native", the default) or an
enabled integration. Dual ownership is structurally impossible.

**Effect of ownership:** If WorkDiary leads, imports from
integrations remain allowed through the inbox as usual. If an
integration leads a domain, only that integration may write there –
write attempts by other integrations land in the inbox as conflicts
instead of changing data. Every ownership change is audited.

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
