---
title: "Cloud backup targets"
topic: backup-targets.overview
version: 2
keywords:
    - offsite backup
    - data backup
    - Dropbox
    - OneDrive
    - Google Drive
    - encrypted backup
    - 3-2-1 rule
    - restore
    - restore test
    - master key
    - backup retention
    - disaster recovery
    - Nextcloud
    - WebDAV
    - S3
    - Seafile
audience: []
related:
    - admin.integrations
---

WorkDiary backs up the whole installation encrypted to Dropbox, OneDrive, Google Drive, Nextcloud, a WebDAV server, S3-compatible storage or Seafile (the offsite copy of a 3-2-1 strategy). Plaintext never leaves the installation — only encrypted parts with a signed commit manifest are uploaded.

**Connections:** Only the platform operator manages backup targets; Dropbox, OneDrive and Google Drive each get their own OAuth account (separate from document intake, dedicated write scopes); Nextcloud, WebDAV, S3 and Seafile use their own credentials. When connecting, WorkDiary writes, reads and deletes a probe file once; only then is the target active. If a required permission is missing, the target is visibly blocked.

**Seafile:** Server URL (HTTPS only), the account token of a dedicated Seafile user and the name or ID of an existing, unencrypted library. WorkDiary only writes into its own folder within it and checks the size of every uploaded file. The account token comes from the Seafile API: POST to /api2/auth-token/ with user name and password, with two-factor sign-in also with the header X-Seafile-OTP and the current code.

**Keys:** BACKUP_MASTER_KEY (ENV, store offline!) is the only regular decryption path; an optional recovery key pair decrypts in an emergency. Without a recovery key the page warns permanently — losing the master key renders all backups useless.

**Operations:** The nightly run creates a snapshot (DB dump + files), encrypts it, uploads parts resumably and applies retention (7 daily / 4 weekly / 12 monthly; legal hold protects individual generations). A weekly spot-check verifies signature and hashes; the restore test restores into an isolated directory and logs RPO/RTO — until the first green test a generation counts as “backed up, restore not yet confirmed”.
