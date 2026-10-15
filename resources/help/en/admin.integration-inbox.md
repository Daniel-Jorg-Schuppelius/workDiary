---
title: "Mapping inbox"
topic: admin.integration-inbox
version: 3
keywords:
    - integration inbox
    - import conflicts
    - reconciliation
    - match records
    - unmatched records
    - field conflict
    - sync conflict
    - unknown phone number
    - unknown devices
    - mapping
    - review imports
    - invoice inquiry
audience: []
related:
    - admin.integrations
    - admin.import
    - contacts.manage
    - finance.open-times
---

The mapping inbox collects **incoming imports that could not be matched
automatically** – from connected systems, the CSV import and the email intake.
Nothing is created blindly: you decide for each entry.

**Three cases:**

- **Unmatched** – there is no matching record for the incoming one.
- **Ambiguous** – several records are possible matches.
- **Field conflict** – the record is known, but the local and the remote
  state contradict each other. Both states are shown side by side.

**Number already assigned:** A special field conflict. An accounting
program assigned a number to a customer or supplier that another record in
WorkDiary already carries. The case names the holder. Change the number
there, then choose **Adopt remote**; the next sync also takes it over on
its own.

**Deciding:** You link an entry to an existing record, create it as a new one
or discard it. For a field conflict you choose whether the local state stays
or the remote one is applied. The decision remains visible on the entry
(Linked, Created, Keep local, Remote applied, Discarded); use the status
filter to bring up resolved entries again.

**Groups:** Entries that belong together appear at the top as a group and are
decided in one step:

- **Imported time** of an unknown project: choose or name the customer,
  optionally the end customer, and the project, then book the group.
- **Unknown devices** from remote support: bind them to a device and book.
- **Unknown phone numbers:** assign them to a customer; "Remember number
  permanently" applies to future calls, a shared number only to this one.
- **Unknown users** of a time import: assign them to a user.
- **Recurring appointments** from calendars: create all as appointments.
- **Orders** from the B2B catalogue: book as order.

"Show entries" expands what is behind a group. A group can also be dismissed
as a whole.

**Filters:** The tabs separate by source; the number is the count of open
entries. You can also filter by status, case and entity. Very long selection
lists are cut to 1000 entries – the search field at the top right narrows the
selection.

**Manage mappings:** WorkDiary remembers a mapping once made; future imports
of the same record then pass without asking. Under "Manage mappings" you view
and remove these links.

**E-mails:** An entry from the e-mail intake can be taken over as a
communication note on the customer, as a service ticket, into the document
storage or as an incoming invoice. If WorkDiary recognised an invoice or
document number but did not assign the e-mail by itself (several numbers,
unknown sender), the entry shows the candidates; **Record as invoice
inquiry** takes the e-mail with the entered number as an inquiry about the
invoice or the accounting program's document. **Book as note on the order**
attaches the e-mail to an open order, for example when a customer replies to
the invoice but means the order.

The page is open to people who are allowed to manage billing.
