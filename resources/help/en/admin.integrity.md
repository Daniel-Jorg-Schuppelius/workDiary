---
title: "Source code integrity"
topic: admin.integrity
version: 1
audience:
    - admin
related:
    - admin.security
---

**Source code integrity monitoring** (feature 095) detects tampering with the
installation's files: every source file is checked via SHA-256 against a
**baseline**, `vendor/` as one checksum per package. The baseline's root hash
is part of the signed release manifest — a tampered baseline fails the
signature check.

- **Status panel**: result of the latest verification run, baseline source
  (release = signable, local = drift detection from the freeze point) and the
  root hash.
- **Verify now** queues a verification run; the result appears in the
  findings list and in the `audit_logs` hash chain.
- **Freeze baseline** creates a new local baseline — required after
  legitimate changes (hotfix, `composer dump-autoload`), otherwise every run
  reports a permanent deviation.
- **Alerts**: platform admins are notified on new or changed findings; the
  all-clear follows the next clean run.
- **Limits**: verification detects, it does not prevent. An attacker with
  full server control can also attack the verifier — external monitoring
  (`integrity:verify --json`, exit code) and OS hardening (read-only mounts,
  AIDE) remain recommended. `.env` and `storage/` are deliberately not part
  of the baseline.

The daily run can be toggled via `INTEGRITY_CHECK_ENABLED` and rescheduled on
the scheduler page.
