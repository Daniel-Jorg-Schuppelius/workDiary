---
title: "Claims & warranty"
topic: claims.overview
version: 4
keywords:
    - complaint
    - customer complaint
    - warranty claim
    - goodwill gesture
    - product return
    - RMA
    - returns handling
    - defect notice
    - supplier recourse
    - return authorization
    - complaint rate
audience: []
modules:
    - module.claims
related:
    - documents.manage
---

The module manages complaints, warranty cases, goodwill decisions and
returns as traceable case files — from intake through assessment and
decision to inventory, service and invoicing consequences.

**Case file:** Every claim gets its own number (REK-…), deadlines, owners
and links to the affected order, project, asset, article, serial number,
invoice and supplier. The domain modules stay authoritative — the case
file links, it never overwrites.

**Linking affected objects:** In the “New claim” dialog and via “Edit” on
the case file you select invoice, order, project, service ticket, asset,
protocol and supplier, and with the inventory module also article and
purchase order. Once a customer is selected, the lists show only that
customer’s objects. A serial number known in inventory is linked to the
case automatically. On invoices, orders, assets and service tickets, “Open
claim” opens the dialog with the object and customer pre-filled.

**Assessment & decision:** Claim kind (guarantee, statutory or contractual
warranty, goodwill, transport damage, misuse, supplier fault) with a
mandatory justification. The facts (serial-number check, deadlines, B2B
notice date under § 377 HGB) are frozen as a snapshot. Decisions require
an active assessment and are auditable; there is deliberately no automatic
claim decision.

**Returns (RMA):** Returns get an RMA number, goods receipt lands in
quarantine (blocked/quality stock), inspection documents findings and the
serial-number check. The disposition (restock, repair, return to supplier,
scrap, dispose) posts idempotently through the inventory ledger.

**Stock reference of the return:** Announcement and goods receipt take
item variant, quantity, warehouse, serial number and lot, pre-filled from
the article and serial number of the case. With variant, warehouse and
quantity, the goods receipt posts the stock into quarantine; the
disposition transfers it or writes it off. Without a variant or without
the inventory module the return is only documented, and the message reads
“recorded without a stock posting”. Returns from the customer portal bring
variant and serial number with them; you add the warehouse at goods
receipt.

**Actions and follow-up order:** Create rework, repair, replacement or a
service visit under “Measures” with a deadline and a responsible person. Each
action can name its follow-up order — an order for the customer of the case
that you are allowed to open. The visit runs through dispatch and tour planning
via this order; the case links it, and you can change or remove it together
with the status of the action.

**Financial outcomes:** Price reduction, credit note, cancellation,
correction, replacement invoice or refund are proposed, approved under the
four-eyes principle and only then executed. Documents are created in the
invoicing module (credit note/cancellation as draft) with a structured
reason flag — there is no separate document type.

**Invoice for the outcome:** In the “Propose outcome” dialog you select
the invoice, pre-filled with the invoice of the case. If you issue the
invoices yourself, every outcome with a document needs this invoice: a
cancellation requires an issued, unpaid invoice, a credit note a paid one.
If an external system manages the invoices, the correction document is
created there and you enter its number afterwards.

**Supplier recourse:** Your own claim against the upstream supplier with
purchase-order/incoming-invoice reference, response deadline and cost
recovery.

The “Create recourse” dialog takes supplier, purchase order, incoming
invoice, article, serial number, warranty terms, amount claimed and an
individual response period in days. Without an entry the default period
applies; it starts with the status “submitted”.

**Reporting:** The quality report shows rate, causes, affected articles,
suppliers, costs, processing time and repeat defects; report states can be
frozen as evidence.

**Customer portal:** Customers see the status of their own cases and can
submit follow-ups — internal assessments and amounts stay hidden.

**Batch on the case:** For batch-tracked articles you select the affected batch in the claim dialog. Without a selected article, the case takes the article of the batch; the “same batch” pattern detection evaluates the field.

## Reject a proposal

Whoever approves commercial outcomes can also **reject** a proposal. It ends without a document and can be neither approved nor executed; a new proposal remains possible.
