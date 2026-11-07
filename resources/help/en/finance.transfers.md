---
title: "Invoicing transfer"
topic: finance.transfers
version: 5
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
    - related documents
    - document chain
    - invoice at provider
    - send evidence
    - delivery file
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

## Related documents

At the bottom of the page, **Related documents** lists every document
connected to this one: the quote and its versions, invoices, down payments and
the final invoice, credit notes and cancellations, delivery notes,
manufacturing orders and handovers. If an accounting program has taken over a
document, its counterpart appears there with the program's number. Links the
program reports itself, such as a down payment created in Lexoffice, appear as
well. The **Relation** column says what the document is to this one; "via …"
names the document through which the connection exists.

## Invoice at provider: evidence and delivery by WorkDiary

If Lexware Office, sevDesk, easybill or orgaMAX keeps the invoice, number
and content stay there. WorkDiary can still add activity reports to the
finalised invoice and deliver it, without an invoice of its own.

1. Finalise the draft in the invoicing system.
2. In the transfer record, click **Fetch invoice from …** in the card
   **Invoice at provider**. WorkDiary fetches number, status and the original
   and assigns the positions of the transfer. For split, bundled or invoices
   created directly in the invoicing system, use **Assign another invoice**.
3. If the positions at the provider differ, confirm the difference on the
   invoice page with a reason. Without confirmation, delivery is blocked.
4. Create or upload evidence in the card **Supporting documents** and click
   **Deliver**.

WorkDiary supplements the original in its own format: a PDF goes out
unchanged with the evidence as attachments, an XRechnung carries it
embedded, a ZUGFeRD PDF is rewritten with supplemented XML. Signed originals
are never changed. The **delivery file** keeps every version with hashes,
evidence and delivery attempts. **Retry** sends the same files; if an
attempt gets no response, check with the recipient whether it arrived.
Evidence created later goes out via **Send evidence later**.

This requires the dispatch route “WorkDiary delivers” in the customer record
or the organisation settings. Then switch off automatic sending in the
invoicing system, otherwise the customer receives the invoice twice.
