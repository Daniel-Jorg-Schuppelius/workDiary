---
title: "Glossary: core terms"
topic: glossary.core
version: 1
audience: []
related:
    - isms.overview
    - exports.payroll
    - finance.transfers
---

The most important terms in WorkDiary – briefly explained.

- **Acceptance (Abnahme)**: documented confirmation by the customer
  that a service has been delivered and accepted – usually via a
  signed protocol.
- **Protocol**: structured evidence document for a work order (e.g.
  maintenance protocol) with positions and an optional signature.
- **Procedure**: defined sequence of steps for a work order – with
  mandatory steps in a fixed order, evidence steps and four-eyes
  confirmations.
- **SLA**: service level agreement – agreed response and resolution
  times per customer or contract.
- **Time account/flex**: running balance of target vs. actual working
  time (over-/under-hours).
- **Month closure**: employees submit their month, the team lead
  approves it; after the time export the month is locked.
- **Tenant/organization**: isolated unit in WorkDiary – every piece
  of information belongs to exactly one organization.
- **Scope**: the part of the organization the ISMS applies to
  (default: "Entire organization"); SoA, risks and audits are kept
  separate per scope.
- **Requirement vs. control**: the requirement describes WHAT a
  standard demands (e.g. "A.5.1"); the control describes, in a
  standard-neutral way, HOW you implement it. Both can be linked
  many-to-many.
- **SoA**: Statement of Applicability – per scope, the statement for
  each requirement: applicable yes/no, justification, implementation
  status, evidence.
- **Audit package**: finalized, immutable data snapshot for a
  reporting date (with SHA-256 hash) for auditors – accessible via
  time-limited auditor links.
- **Invoicing path**: the leading invoicing program per
  organization/customer (DATEV, Lexoffice or local). WorkDiary hands
  over positions – the invoice is created in the leading system.
