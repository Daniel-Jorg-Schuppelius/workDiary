---
title: "SEPA outgoing payments"
topic: finance.sepa
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.incoming-invoices
    - invoices.manage
    - contacts.manage
---

A payment run bundles released incoming invoices into a SEPA bulk credit
transfer. workDiary produces a **file, not a payment order**: the payment is
triggered in the banking program under its own authorisation.

**Payment proposal:** The list holds every open incoming invoice released for
payment. For each one the most economical execution date is proposed — the
discount date while it is still reachable, otherwise the due date. The
payment amount is then already reduced by the discount. Every position can be
deselected; an invoice without an IBAN is shown as blocked and is not taken
into the run.

**Three steps:** compile (draft) → release → export. Releasing is a separate
right: whoever compiles the run need not be allowed to release it. After the
export the run is immutable; cancelling is possible only before that and
makes the invoices payable again.

**Deduction:** Individual positions can be set to a lower amount while the
run is a draft — for a defect retention towards the supplier, for instance. A
reduced payment amount requires a reason; invoice amount and payment amount
then stand side by side.

**Proof:** The generated file is archived as a confidential document and its
SHA-256 hash recorded on the run. A second retrieval returns the same file —
never a new one with a different message ID, which the bank could read as a
second payment.

**Mandates and collection:** For direct debit the mandate register keeps the
mandate reference, signature date and kind (one-off/recurring). A mandate is
never deleted but revoked — the revocation is the evidence of when collection
was no longer permitted. After 36 months without a collection a mandate
lapses. The lead time is five banking days for the first collection and two
for a subsequent one. Collection requires the organisation's creditor
identifier (setting “creditor identifier” in the settings registry).

**Add-on module:** File generation belongs to the paid banking-format module.
Without it the payment run and mandate register stay usable; only the export
is missing.
