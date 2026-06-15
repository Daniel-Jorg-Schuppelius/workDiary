---
title: "Security & hardening"
topic: admin.security
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.backups
    - isms.software
---

The most important security tools for operations:

**Security overview**: the admin page "Security"
(`/admin/security`) bundles the security-relevant state read-only:
active sessions, API tokens (metadata only – never the token value),
active external integrations, the most recent data/time exports, the
most recent support accesses (audit events with the `support.`
prefix), plus 2FA coverage and the at-rest encryption status. The
page only displays and never changes any security objects; the
automated deletion and retention runs are not part of this overview.

**Two-factor authentication**: users can register several methods in
parallel – **TOTP** (authenticator app), **e-mail code** and
**WebAuthn** (FIDO2 security key/passkey). Recommend at least two
methods so losing one factor does not lock anyone out.

**Encrypting existing data**:
`php artisan security:encrypt-existing` (with `--dry-run` for
testing) encrypts existing sensitive fields (including tax/social
security numbers, IBAN/BIC, addresses). The run is idempotent and
skips values that are already encrypted.
**Caution**: encryption depends on the **APP_KEY** – take a backup
before the run and store the key separately; without the APP_KEY the
data is unrecoverable.

**Verifying the audit chain**: `php artisan audit:verify` validates
the SHA-256 hash chains of the tamper-evident audit logs and exits
with code 1 on a break – ideal for cron/CI. Keep this command
permanently green.

**System health**: `php artisan system:health` checks database,
migrations, storage, queue, APP_KEY, mail and license without
changing any data.

**Components & SBOM**: the components overview in the administration
area shows the app, PHP, Laravel and DB versions, modules and plugins
and generates an **SBOM** (CycloneDX 1.5) from the lock files – as a
download for audits. Access is limited to global admins.
