---
title: "SharePoint storage"
topic: admin.sharepoint
version: 2
keywords:
    - SharePoint
    - SharePoint Online
    - document library
    - mirror documents
    - store files in SharePoint
    - Microsoft 365
    - select site
    - mirroring
    - proof of handover
    - file invoices
    - mirror conflict
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.msgraph
    - documents.manage
    - admin.integration-inbox
    - cloud-intake.overview
---

The **SharePoint storage** page mirrors released documents from WorkDiary into
a SharePoint Online document library via Microsoft Graph – optionally also the
PDFs of issued invoices and signed protocols. WorkDiary stays in charge:
nothing flows back from SharePoint, and changes to mirrored files in
SharePoint show up as a conflict and are never adopted silently. For every
transfer WorkDiary records a proof of handover (checksum, time, target).

Fetching files from SharePoint into WorkDiary in read-only mode is a different
feature: the **Cloud document intake**.

## Prerequisites

- The **SharePoint** plugin is activated under **Plugins**. After that, the
  **SharePoint storage** entry appears in the system menu (gear icon
  **System**) in the **Plugins** group.
- There is an app registration in Microsoft Entra ID with a client ID and
  client secret. Either the operator has stored an app for the whole
  installation, or your organization uses its own app from the settings of the
  **Microsoft 365** plugin (**Client ID (own app registration)**, **Client
  secret**, **Tenant (directory ID)**). An own app must know your WorkDiary
  address with the path /admin/sharepoint/oauth/callback as a redirect URI of
  type “Web”. If the app is missing, the page shows a notice instead of the
  connect button.
- You need a Microsoft 365 account with write access to the target library.
  The connection works with the permissions of this account. If the operator
  has restricted access to individually granted sites (Sites.Selected), a
  tenant administrator must also grant the desired site.
- The page is open to administrators of your organization. Each organization
  has one SharePoint connection.

## Connecting

1. Click **Connect with Microsoft 365**. The Microsoft sign-in opens; sign in
   and consent to the permissions.
2. Microsoft returns you to the page. The message “Connected with Microsoft
   365. Now choose site + library.” confirms the connection.

The same person who started the process must finish it in the same session.
Otherwise “The OAuth flow expired or is invalid” appears; in that case start
the connection again.

## Choosing the target: site and document library

1. In the **Target: site + document library** section, enter the name or a
   keyword of the site in the **Search site** field and click **Search**.
2. Click the site in the list of results. It is marked as **Selected**, and
   WorkDiary loads its document libraries.
3. Under **Document library**, select the library and click **Save**.
   **Current target** then shows the site and library.

When saving, WorkDiary double-checks the site and library with Microsoft; a
library that does not belong to the selected site is rejected.

## Folder rules and mirrored content

In the **Folder rules + sources** section you define what is mirrored where:

- **Default folder** (prefilled with “Dokumente”): subfolder of the library for
  all documents without a rule of their own.
- **Active**: switches mirroring on or off.
- **Mirrored content**: **Documents (DMS)**, **Invoices (PDF)** and
  **Protocols (PDF)**. Without a selection, only documents are mirrored.
- **Document type → folder**: per row, select a document type and enter a
  subfolder relative to the library. Empty rows are ignored; after each save,
  three more empty rows are available. The types appear in the list with
  their label, for example Contract or Invoice.

Then click **Save**.

This is how WorkDiary stores the files:

- **Documents** in the folder of their type or in the default folder. The file
  name consists of “document-”, an internal number and the file extension; a
  new version therefore replaces the same file.
- **Invoices** in the invoices folder, with one subfolder per year; the file
  name is the invoice number.
- **Protocols** in the protocols folder, with one subfolder per year.

Invoices and protocols do not follow the folder rules.

## When mirroring happens

- **Automatically on events:** when a document gets the status **Active**
  (released) or a new version, WorkDiary transfers that version. Pure changes
  to metadata do not trigger a new transfer. When an invoice is issued or a
  protocol is signed, its PDF follows. All of this applies only to the
  selected content; without **Documents (DMS)** ticked, WorkDiary transfers no
  documents.
- **In the background with retries:** the transfer runs through a queue. If it
  fails, it is retried automatically; no file is written twice.
- **Mirror now:** queues everything from the selected content – active
  documents, issued invoices and signed protocols –, for example after the
  initial setup, also for records from before. WorkDiary skips unchanged
  files.

There is no fixed schedule. WorkDiary only reads from SharePoint to check
whether a mirrored file was changed there.

## Resolving conflicts

If a mirrored file was changed in SharePoint, WorkDiary does not overwrite it.
Instead, an entry appears in the **Mapping Inbox** with the notice “External
change detected — mirroring paused (no overwrite).” For documents from the
document management, three actions are available:

- **Overwrite remote**: the WorkDiary version replaces the file in SharePoint;
  the change made there is lost.
- **Import as new version**: the SharePoint version is adopted as a new version
  of the document.
- **Detach mirroring**: this one document is no longer mirrored; the
  connection stays active.

For invoice and protocol PDFs there is only **Overwrite remote**: issued
invoices and signed protocols cannot be changed, so WorkDiary stores its PDF
again. If you want to keep the changed file, choose **Dismiss**.

The **Mapping Inbox** is open to people who are allowed to manage billing.

## Disconnecting and reconnecting

**Disconnect** removes the access keys of the connection. Files already
mirrored remain in SharePoint. The target and folder rules stay saved; after
**Connect with Microsoft 365** again, everything continues with the same
settings.

## Typical problems

- **No connect button:** the page reports a missing app registration. Store
  the client ID and client secret (see prerequisites) or contact the operator.
- **Sign-in cancelled:** “Microsoft did not return an authorization code” – the
  sign-in was cancelled or consent was refused. If your tenant requires an
  administrator's consent, an Entra administrator must grant the permission to
  the app.
- **No sites found:** check the search term. With restricted access, the
  tenant administrator must grant the site.
- **Site or library rejected:** “The chosen site is unreachable or not
  granted.” or “No document libraries found in this site.” – the connected
  account has no access, or the site has no library.
- **Status Inactive, Mirror now missing:** the connection is disconnected,
  **Active** is switched off, no library is selected, or the connection was
  shut down after repeated consecutive errors. Once the cause is fixed,
  **Disconnect** and connecting again reset the error count. As long as the
  connection is failing, an operations task is listed for it.
- **Checking the state:** next to the page title you see the most recently
  checked state; **Test connection** checks it right away.
