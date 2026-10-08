---
title: "WebDAV storage"
topic: admin.webdav
version: 1
keywords:
    - WebDAV
    - Nextcloud
    - ownCloud
    - mirror documents
    - file storage
    - store invoices
    - store protocols
    - app password
    - folder rules
    - mirror conflict
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - documents.manage
    - invoices.manage
    - protocols.sign
    - backup-targets.overview
---

The **WebDAV** page (page title **WebDAV storage**) mirrors released
documents and, if you wish, issued invoices and signed protocols as files into
an external WebDAV storage such as Nextcloud or ownCloud. For every file
WorkDiary keeps a transfer record (checksum, time, target). WorkDiary remains
the leading system: there is no return channel, and changes to mirrored files
in the storage surface as a conflict instead of being taken over silently.
You find the page in the system menu (the **System** gear in the header)
under **Plugins** → **WebDAV** once the plugin is active.

## Prerequisites

- The plugin is activated for your organization: **System** → **Plugins** →
  **Plugins**, then **Activate** on the WebDAV entry. You set up the storage
  itself on the **WebDAV** page, not in the plugin dialog.
- The page is open to administrators.
- You need an account in the storage with write access to the target folder
  and an app password (Nextcloud: Settings → Security → App password).
- The storage must be publicly reachable. WorkDiary rejects addresses on an
  internal network.
- There is exactly one WebDAV storage per organization.

This page is not a target for backups. You set up a WebDAV backup target
under **Cloud backup targets**.

## Setting up the storage

In the **Storage** section you fill in:

- **Label**: a name of your choice.
- **Collection URL**: the full WebDAV folder WorkDiary writes to, for
  Nextcloud something like …/remote.php/dav/files/USER/WorkDiary. The address
  must start with http:// or https://; create the folder in the storage
  beforehand.
- **Username** and **App password**: the password is required the first time
  you save and is stored encrypted; later, an empty field keeps the stored
  password.
- **Default folder**: subfolder for documents without their own folder rule
  (prefilled with Dokumente).
- **Active**: switches the storage on or off.
- **Mirrored content**: **Documents (DMS)**, **Invoices (PDF)**, **Protocols
  (PDF)**.
- **Document type → folder**: a separate subfolder per document type, see
  below.

**Save** applies your entries. When the storage is active, the page shows its
health (for example **Health ok**) and **Test connection**.

## What is mirrored and when

- **Documents:** a document is mirrored as soon as it has the status
  **Active** with a file, and again with every new version. Changes to the
  details without a new version do not trigger an upload. The storage always
  mirrors released documents while it is active – even if **Documents (DMS)**
  is not ticked.
- **Invoices (PDF):** with this box ticked, every invoice is stored once as a
  PDF when it changes to **Issued**.
- **Protocols (PDF):** with this box ticked, every protocol is stored as a
  PDF when it is signed (status **Signed**).
- The transfer runs in the background through a queue and is retried on
  connection errors. WorkDiary does not upload unchanged content again.
- **Mirror now** queues all currently released documents again – useful after
  the setup. This button does not cover invoices and protocols; they are only
  mirrored from the setup onwards, when they are issued or signed.
- There is no scheduled run; mirroring follows the changes in WorkDiary.

## Folders and file names

- Documents are stored in the folder of their document type from **Document
  type → folder**, otherwise in the **Default folder** – both relative to the
  collection URL. The file is named document- followed by the document number
  and the original extension, for example document-42.pdf.
- The document type selection currently shows the short English code, for
  example contract for contracts or invoice for invoices. There are always
  three empty rows; WorkDiary discards rows without a type or without a
  subfolder.
- Invoices are stored under invoices/year/invoice-number.pdf, protocols under
  protocols/year/protocol-number.pdf – directly below the collection URL, not
  in the default folder.
- WorkDiary creates missing subfolders itself.

## Conflicts

Before WorkDiary uploads a new version, it checks whether the file in the
storage has been changed since the last mirror run. If so, it overwrites
nothing and creates a conflict in the Mapping Inbox: “External change
detected — mirroring paused”. There you choose:

- **Overwrite remote**: the file in the storage receives the state from
  WorkDiary; the external change is lost.
- **Import as new version**: the state from the storage becomes the new
  version of the document in WorkDiary.
- **Detach mirror**: this document is permanently no longer mirrored; the
  storage stays active for all others.

The Mapping Inbox is open to administrators and accounting.

## Disconnecting

**Disconnect** switches the storage off. Files already mirrored stay in the
storage. To switch it on again, set **Active** and save.

## Common errors

- “The collection URL must start with http:// or https://.”: enter the full
  address.
- “A new storage requires an app password.”: the password is missing the
  first time you save.
- **Health failing** with “WebDAV storage unreachable or credentials
  invalid.”: check the collection URL, username and app password, and whether
  the folder exists. A WebDAV error with RuntimeException often points to an
  address on an internal network.
- “No active WebDAV storage.” on **Mirror now**: the storage is off or
  incomplete.
- Invoices or protocols are missing in the storage: the matching box under
  **Mirrored content** was not ticked when they were issued or signed.
- A document is no longer updated: there is an open conflict in the inbox,
  or its mirroring has been detached.
