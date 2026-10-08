---
title: "Security & hardening"
topic: admin.security
version: 2
keywords:
    - 2FA
    - passkey
    - encryption at rest
    - IP ban
    - brute force
    - SIEM
    - hacked account
    - account takeover
    - secure account
    - SBOM
    - security overview
    - intrusion detection
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

## Temporary IP ban and SIEM export

Without fail2ban on the server, WorkDiary can itself temporarily ban
addresses after repeated failed attempts (environment variable
`SECURITY_IP_BAN`, off by default): 15 minutes, one hour on repetition,
then 24 hours. Signed-in sessions and private networks are never affected;
you see and lift active bans under "Threat detection". Because many users
can share one address (mobile networks, company networks), fail2ban remains
the first choice. For a SIEM, WorkDiary additionally writes every security
event in CEF or JSON format to a separate file or via syslog
(`SECURITY_SIEM_FORMAT`, `SECURITY_SIEM_TARGET`).

## Securing an account after a takeover

If it is confirmed that someone has taken over an account, signing out is not
enough: whoever knows the password signs in again. “Secure account” in session
management (for members of your organisation) and “Confirm account takeover”
on a security event (platform administration) end all sessions and API tokens,
invalidate the password and all passkeys and send the person a link to set a
new password. App-based two-factor methods are kept. The action appears as a
security event and in the audit log. You secure your own account on your
two-factor authentication page.
