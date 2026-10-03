---
title: "Mapping inbox"
topic: admin.integration-inbox
version: 1
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

The page is open to people who are allowed to manage billing.
