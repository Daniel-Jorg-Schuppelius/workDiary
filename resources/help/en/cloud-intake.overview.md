---
title: "Cloud document intake"
topic: cloud-intake.overview
version: 2
keywords:
    - Paperless-ngx
    - Seafile
    - Dropbox
    - OneDrive
    - SharePoint
    - Google Drive
    - watched folder
    - document import
    - cloud folder
    - folder rule
    - automatic import
    - connect cloud storage
audience: []
related:
    - documents.manage
    - admin.integrations
---

WorkDiary READS documents from monitored folders in Dropbox, OneDrive/SharePoint, Google Drive and Nextcloud, and from Paperless-ngx and Seafile, and routes them to invoice intake or the DMS via folder rules.

**Connections:** One account per provider is connected and confirmed via OAuth; then container (drive/library) and root folder are selected. Imports only run once at least one valid rule exists.

**Paperless-ngx:** Base URL and API token are kept in the plugin settings; “Connect Paperless-ngx” checks the account. As container, choose all documents or one tag, correspondent or document type. A document’s path is document type/correspondent/file name (“-” if unassigned); “/” is enough as root folder. The original file is taken over, so embedded e-invoice data is kept; the origin on the record links back to the Paperless original. Documents tagged later are picked up by the next run when it compares the document list. The token reads with the rights of its user: it needs read access to documents, tags, correspondents, document types and UI settings; only what this user may see is taken over.

**Seafile:** Address and API token are kept in the plugin settings; “Connect Seafile” checks the account. As container, choose a library and the root folder within it; encrypted libraries are not offered. If nothing below the root folder has changed since the last run, WorkDiary reads no folder. WorkDiary recognises a file by its content: renaming or moving it creates no new document, a file with changed content does. The origin on the document links to the file in Seafile. The account token comes from the Seafile API: POST to /api2/auth-token/ with user name and password, with two-factor sign-in also with the header X-Seafile-OTP and the current code.

**Rules:** Path patterns with * and ** plus variables like {customer_number} assign files to existing customers, projects, orders, assets or contracts — never by auto-creation. Unclear matches go to the integration inbox.

**Security:** Read-only scopes, encrypted tokens, source files remain untouched; webhooks are wake-up signals only — the resumable delta run is authoritative.
