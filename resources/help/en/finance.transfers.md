---
title: "Invoicing transfer"
topic: finance.transfers
version: 3
keywords:
    - Lexoffice transfer
    - send times to Lexoffice
    - DATEV handover
    - create invoice draft
    - transfer services
    - bill materials
    - bill hours
    - billing handover
    - invoicing export
    - leading invoicing system
    - transfer line items
audience: []
modules:
    - module.finance
related:
    - exports.payroll
    - admin.surcharge-rules
    - roles.buchhaltung
    - glossary.core
---

The invoicing handover transfers billable **times** and **materials**
to the leading invoicing system. You find it in the menu under
**Invoicing handover**; the **Transfer receipts** page lists all
transfers.

Core principle of invoicing authority: **the invoice is created in the
leading external program** (e.g. Lexoffice, orgaMAX, sevDesk, easybill
or DATEV) – WorkDiary only supplies reviewed positions together with a
transfer receipt. A local invoice in WorkDiary exists only if no
external invoicing software is in use. Exactly one **Billing channel**
applies per organization or customer.

Typical workflow:

1. **Prepare transfer** (status **Draft**): choose the **Customer**,
   the **Transfer channel** – **Services/time** or
   **Products/material**, kept separate –, the **Transfer target** and
   the **Service period**. The target is preset from the customer's
   billing channel: **Lexoffice** (invoice draft), **orgaMAX (order)**,
   **sevDesk (invoice draft)** or **easybill (invoice draft)**; **File
   export** is always available as well. If DATEV leads, the handover
   is a file package (CSV) via the file export.
2. Review the positions and **Confirm transfer** (status
   **Confirmed**). Only then can you edit the name and service text and
   merge or remove positions.
3. **Transfer now** → status **Transferred** (final). On **Failed**,
   **Retry** sets the transfer back to **Confirmed**; then you transfer
   again.
4. Transfers in status **Draft** or **Confirmed** can be voided with
   **Void transfer** – the contained positions are released again.

Risks and irreversible actions:

- **“Transferred” is final** – the contained positions are locked
  against changes.
- Corrections run through traceable steps, never through a silent
  reset: **Cancel transfer** releases the sources again, **Create
  correction** creates a correction transfer with the same times. A
  draft created at the target is not deleted in the process – remove
  it there by hand.

Permissions: time and material transfers are protected separately
(**Prepare and transfer billable time** or **Prepare and transfer
billable material**). People with **View transfer receipts** see the
list; cancelling and correcting additionally require **Manage finance
configuration**.
