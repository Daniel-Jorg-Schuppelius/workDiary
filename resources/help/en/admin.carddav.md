---
title: "Connecting a CardDAV address book"
topic: admin.carddav
version: 1
keywords:
    - CardDAV
    - connect address book
    - Nextcloud contacts
    - Radicale
    - Baïkal
    - import contacts
    - match contacts
    - app password
    - vCard
    - contact sync
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
    - admin.integration-inbox
    - contacts.manage
    - admin.scheduler
---

The **CardDAV** page connects WorkDiary to an address book on your own
CardDAV server, such as Nextcloud, Radicale or Baïkal. WorkDiary only reads the
contacts and proposes them for matching with your customers. It never writes
anything back to the address book, never merges records on its own and never
creates customers. You do not need a Microsoft or Google account for this.

## Prerequisites

- The **CardDAV** plugin is activated for your organization under
  **Plugins**. After that, the **CardDAV** entry appears in the system menu
  (gear icon **System**) in the **Plugins** group.
- You know the address of the CardDAV server, a username and a password. If
  two-step sign-in is enabled on the server (common with Nextcloud), you need
  an app password, which you create in your account on the server.
- The page is open to administrators of your organization. Each organization
  has exactly one CardDAV connection.

## Setting up the connection

1. Fill in the fields in the **Connection** section:
   - **Name**: a name for the connection, for example “Nextcloud office”.
   - **DAV base URL**: for Nextcloud the address up to and including
     /remote.php/dav, for Radicale and Baïkal the root of the server. The
     address must start with http:// or https://.
   - **Username** and **App password**. The password is stored encrypted and
     never shown again. If you leave the field empty for an existing
     connection, the stored password remains valid.
   - **Allow private/internal addresses**: only switch this on if the server
     is on your own network (for example 192.168.x.x). Without this switch,
     WorkDiary rejects internal addresses. Switching it on is logged.
   - **Active**: switches the connection on or off.
2. Click **Save**.
3. Click **Find address books** at the top. WorkDiary queries the server and
   lists the address books it found in the **Address book** section.
4. Select an address book and click **Use address book**. From now on it is
   the sync source; the page shows it as “Current sync source”.

Only address books from the last search that live on the same server as the
base URL can be selected. An arbitrary address cannot be entered as a source.

## What is synchronized and when

- **Direction:** only from the CardDAV server to WorkDiary.
- **Content:** name, company, email address, phone, mobile and fax number,
  note and the postal address including country. If there are several email
  addresses or numbers, WorkDiary prefers the ones marked as work.
- **Timing:** the sync runs automatically every hour. You change the
  interval under **Scheduled tasks**. **Sync now** starts it immediately; it
  then runs in the background, and afterwards the page shows “Last synced …”.
- **Changes only:** WorkDiary skips unchanged contacts. Only new and changed
  cards are processed.

## Matching with customers

- If a contact clearly matches exactly one customer, WorkDiary links the two.
  If a linked contact changes later and its details differ from the customer,
  a field conflict is created in the **Mapping Inbox** – customer data is
  never overwritten silently.
- All other contacts (no matching customer or several candidates) end up as
  proposals in the **Mapping Inbox**. There you assign the contact to a
  customer, create it as a new record or dismiss it.
- If a contact is deleted in the address book, WorkDiary dismisses its proposal
  if it is still open. Assignments already made remain in place.
- The **Mapping Inbox** is open to people who are allowed to manage billing.

## Changing, disconnecting, starting over

- If you change the **DAV base URL**, WorkDiary discards the selected address
  book and the previous sync state. Then search for the address book again and
  select it.
- If you select a different address book, the sync starts from scratch.
- **Disconnect** sets the connection to inactive. Proposals already created are
  kept. To resume, switch **Active** back on and click **Save**.

## Typical problems

- **Internal address rejected:** the message “The base URL points to a
  private/internal address” appears when the server is on your own network.
  Switch on **Allow private/internal addresses**.
- **Search fails:** “Address book discovery failed” means that the server
  cannot be reached or the credentials are wrong. Check the base URL, username
  and app password.
- **No address books:** “No address books were found on the server” – the
  account has no address book, or the base URL points to the wrong level.
- **Foreign address book:** “The address does not belong to the configured
  CardDAV server” – the selected address book is on a different server than the
  base URL.
- **No sync:** if **Sync now** is missing or WorkDiary reports “Sync not
  possible”, the connection is inactive, no address book is selected, or it was
  shut down after repeated consecutive errors. The page shows the last error at
  the top.
- **Checking the state:** next to the page title you see the most recently
  checked state of the connection. **Test connection** checks it right away.
