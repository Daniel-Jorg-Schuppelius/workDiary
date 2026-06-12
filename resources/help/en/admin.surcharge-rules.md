---
title: "Surcharge rules"
topic: admin.surcharge-rules
version: 1
audience:
    - admin
    - buchhaltung
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Surcharge rules define night, weekend, public holiday and custom
time-window surcharges. During the time export, attendances are split
accordingly and reported as separate lines per wage type.

Typical workflow:

1. **Create a rule**: unique code (e.g. "night"), label (e.g. "Night
   surcharge"), kind.
2. Choose the **kind**: "Night" (time window, may cross midnight,
   e.g. 22:00–06:00), "Saturday", "Sunday", "Holiday" (public
   holidays automatically) or "Custom" (free time window).
3. Set the **percentage** (0–999.99 %) and optionally the **wage type
   number** for DATEV/Lexware (e.g. "2010").
4. Optionally set **validity** (from/until), **priority** and
   **active**.

Important rules:

- With overlapping rules the **highest percentage wins** – surcharges
  are not added up. On a tie, priority decides.
- Time windows apply only to the kinds "Night" and "Custom".
- Changes affect **future exports**; already created exports remain
  unchanged (correction via re-export).

Permissions: surcharge rules may only be created, edited and deleted by
explicitly authorized staff.
