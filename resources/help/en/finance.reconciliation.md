---
title: "Payment reconciliation"
topic: finance.reconciliation
version: 1
audience: []
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

**Payment reconciliation** imports bank statements in **CAMT.053** (preferred)
or **MT940** (fallback) format, normalises the transactions in a **review
area** and suggests open invoices or approved expenses for allocation. **The
import alone changes no document** – only **confirmation** sets
`invoice → paid` (with payment date) or marks an expense as reimbursed.

## Workflow

1. **Import:** Upload the bank file (optionally pick one of your own bank
   accounts; otherwise it is auto-matched via the IBAN). Identical files are
   rejected as duplicates via the file hash; already known transactions are
   skipped on re-import.
2. **Review:** In the statement detail each transaction shows a status
   (Open/Allocated/Set aside/Unassignable) and – if open – **allocation
   suggestions** with a score and reasons (invoice number, amount, cash
   discount, IBAN match, date proximity).
3. **Confirm:** *Confirm* creates the allocation and applies the effect to the
   document. Alternatively *Set aside* (e.g. a bank fee) or *Unassignable*.
4. **Undo:** A confirmed allocation is **reversible** – it is removed and the
   document effect (paid/reimbursed) is reverted only if this transaction was
   the payment. **The bank transaction itself is never modified.**

## Practical cases

- **Cash discount:** An underpayment within the discount tolerance (default
  3 %) counts as a full payment.
- **Cent tolerance:** Rounding differences up to 2 cents do not prevent a
  suggestion.
- **Partial/overpayment:** tracked as their own allocation kind; on a partial
  payment the invoice stays open.
- **Balance chain:** Opening balance + sum of transactions is checked against
  the closing balance; mismatches are flagged.
- **Foreign currency:** Transactions in a differing currency are only detected
  and flagged for manual review.

## Data protection

Personal bank data (counterparty name, IBAN, remittance info) is stored
**encrypted**. Matching runs solely on unencrypted derivations (IBAN hash,
extracted invoice numbers, amounts, dates). Every allocation action is
recorded in a tamper-evident hash chain.

## Permissions

- **Import bank file** and **confirm/undo allocations:** the *Accounting*
  role (and administrators).
- **Manage own bank accounts:** administrators only.
