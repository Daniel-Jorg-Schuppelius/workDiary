---
title: "Cloud document intake"
topic: cloud-intake.overview
version: 1
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary READS documents from monitored folders in Dropbox, OneDrive/SharePoint and Google Drive and routes them to invoice intake or the DMS via folder rules.

**Connections:** One account per provider is connected and confirmed via OAuth; then container (drive/library) and root folder are selected. Imports only run once at least one valid rule exists.

**Rules:** Path patterns with * and ** plus variables like {customer_number} assign files to existing customers, projects, orders, assets or contracts — never by auto-creation. Unclear matches go to the integration inbox.

**Security:** Read-only scopes, encrypted tokens, source files remain untouched; webhooks are wake-up signals only — the resumable delta run is authoritative.
