---
title: "GoBD export (data carrier handover)"
topic: finance.gobd
version: 1
audience:
    - admin
modules:
    - module.finance
related:
    - invoices.manage
    - audit.log
---

For tax audits, WorkDiary produces the data carrier handover according to
access type Z3: an audit package in the GDPdU description standard that
the auditor can load directly into their analysis software.

**Package contents:** The package is a ZIP archive containing an
index.xml that describes tables, fields and formats in machine-readable
form, plus semicolon-separated CSV data files. The data sections can be
selected individually: outgoing invoices, invoice items, customer master
data and time records of the audit period.

**Period & preflight:** The previous year is preselected as the audit
period; from/to can be chosen freely. Before the export, a preflight
shows the record counts per section and warns about anomalies — for
example if draft invoices still exist in the period or no invoices are
found at all.

**Character set:** The CSV files are generated in CP1252 ("ANSI", the
default and the safest choice for auditors), ISO-8859-15 or UTF-8; the
description file states the chosen encoding.

**Reproducible hash:** All data is sorted and formatted
deterministically. The package hash is computed over the file contents
(not over the ZIP binary, which contains timestamps) — the same period
with the same sections and encoding therefore reproducibly yields the
same hash. A separate hash is documented per file as well. This makes it
possible to prove beyond doubt that a handed-over package is unchanged.

**Export record list:** Every export automatically creates a
tamper-proof record: who exported which period with which sections and
when, including package and file hashes and the record count. The most
recent exports are visible directly on the page; the full history is
kept permanently and complements the audit log.

The export only reads existing data — it changes neither documents nor
master data and can be repeated as often as needed.
