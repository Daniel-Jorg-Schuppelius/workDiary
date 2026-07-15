---
title: "Cloud backup targets"
topic: backup-targets.overview
version: 1
audience: []
related:
    - admin.integrations
---

WorkDiary backs up the whole installation encrypted to Dropbox, OneDrive or Google Drive (the offsite copy of a 3-2-1 strategy). Plaintext never leaves the installation — only encrypted parts with a signed commit manifest are uploaded.

**Connections:** Only the platform operator manages backup targets; each provider gets its own OAuth account (separate from document intake, dedicated write scopes). If a required permission is missing, the target is visibly blocked.

**Keys:** BACKUP_MASTER_KEY (ENV, store offline!) is the only regular decryption path; an optional recovery key pair decrypts in an emergency. Without a recovery key the page warns permanently — losing the master key renders all backups useless.

**Operations:** The nightly run creates a snapshot (DB dump + files), encrypts it, uploads parts resumably and applies retention (7 daily / 4 weekly / 12 monthly; legal hold protects individual generations). A weekly spot-check verifies signature and hashes; the restore test restores into an isolated directory and logs RPO/RTO — until the first green test a generation counts as “backed up, restore not yet confirmed”.
