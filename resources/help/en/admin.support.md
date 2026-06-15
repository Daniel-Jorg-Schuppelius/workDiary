---
title: "Support report & diagnostics"
topic: admin.support
version: 1
audience:
    - admin
related:
    - admin.security
    - admin.backups
    - admin.handbook
---

The **support report** bundles the technical state of your installation
so support can analyse a problem — **without any customer data leaving
the building**.

What the report contains:

- **Versions & build**: app version, build hash, PHP, Laravel and
  database version, plus the active modules and plugins.
- **Health status**: the result of `php artisan system:health`
  (database, migrations, storage, queue, APP_KEY, mail, license,
  backup) as a compact status block.
- **Plugin errors (7 days)**: plugin id, phase and count only — no
  error texts, no payloads.
- **Operations**: queue depth and the latest backup heartbeats (counts
  and metadata such as size/timestamp only).
- **Record counts**: number of rows per table — never any content.
- **Configuration flags**: which modules/features are active, mail
  transport type, queue driver. Secrets (APP_KEY, passwords, tokens)
  are always redacted.

**Data minimisation is the core promise.** The report only contains
explicitly allowed technical fields (whitelist). Customer names,
personal data, plaintext credentials and secrets never appear.

How to generate it:

- **Admin page** "Support report": ZIP bundle (optionally
  password-protected), plain JSON file, or browser preview.
- **Command line** (on-premise/CI): `php artisan support:report` prints
  the report to STDOUT, `--output=path.json` writes it to a file.

Every generation is recorded in the audit log
(`support.reportGenerated`, `support.reportDownloaded`).
