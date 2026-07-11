---
title: "Claims & warranty"
topic: claims.overview
version: 1
audience: []
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

**Financial outcomes:** Price reduction, credit note, cancellation,
correction, replacement invoice or refund are proposed, approved under the
four-eyes principle and only then executed. Documents are created in the
invoicing module (credit note/cancellation as draft) with a structured
reason flag — there is no separate document type.

**Supplier recourse:** Your own claim against the upstream supplier with
purchase-order/incoming-invoice reference, response deadline and cost
recovery.

**Reporting:** The quality report shows rate, causes, affected articles,
suppliers, costs, processing time and repeat defects; report states can be
frozen as evidence.

**Customer portal:** Customers see the status of their own cases and can
submit follow-ups — internal assessments and amounts stay hidden.
