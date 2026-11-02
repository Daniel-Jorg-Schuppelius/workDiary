---
title: "Recurring templates (plan)"
topic: finance.resale.plan
version: 1
keywords:
    - recurring templates
    - recurring invoice
    - template proposal
    - plan check
    - execution status
    - subscription without template
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.accounts
    - finance.resale.periods
---

**Recurring templates** are the recurring invoices of the accounting program;
the register mirrors them, assigns their lines to subscriptions and checks
stock against plan. The "Template proposal" page names which templates
still need to be created.

**Recurring templates (plan):** If an accounting program issues the invoices,
recurring invoices are created there as recurring templates. The register
mirrors them (Lexoffice: daily and after the voucher sync, read-only) and shows
per template in the license account its status, rhythm, next run and the
licence lines with the subscriptions they invoice. The subscription ↔ template
line assignment is proposed by the run — first via the invoice the template
last created that is assigned to a period, otherwise via same recipient,
article and quantity (with several end customers of a partner only when
named) — you confirm, assign manually or
remove. Periods billed by a running template are left out of the invoice proposal;
whatever ends before its first execution stays in. Old years can thus still
be billed after the template has been created. **Checks stock ↔ plan** report before the invoice: subscription
without template, template without subscription ("Create subscription from
template" takes over article, quantity, rhythm and price), differing quantity,
template running past the subscription end, template paused or ended while the
subscription runs, next run more than seven days off the period start, last run
failed — in the tile, the weekly digest and the work queue. Every mirrored
invoice carries the reference to its template.

**Template proposal and execution status:** The "Template proposal" page
(from the periods list) lists, per recipient with external invoicing
authority, the subscriptions without an assigned template with article, line
text, quantity, unit, unit price, interval and first execution; recipients
covered by the register's recurring run are marked. You create the template
in the accounting program, the next mirror proposes the assignment. Per
template, the license account shows what became of the last execution —
invoice paid, open (check dispatch in the program) or missing — and on a
failed execution the program's message as a tooltip; whether an invoice was
sent by e-mail is unknown to the Lexoffice API. A template line takes at most
as many licenses as it invoices; domain subscriptions are only assigned when
the domain is named exactly.
