---
title: "Club members"
topic: club.members
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - admin.import
---

The member register lists every person in your club — whether or not they
can log in. Children without their own email are members just like adults
with a user account; an account of the same organisation can be linked
without creating a staff role.

**Member number and master data:** Every member receives a sequential number
per organisation. When creating a member you can enter a number (for example
from a legacy list) or leave the field empty. Siblings sharing an address or a
family email remain separate members — the email is not a duplicate key.

**Membership history:** The type (active, passive, supporting, paused) applies
per period. "Change type / pause" closes the current period the day before
the effective date and opens the new one; earlier periods stay visible.
"Record leaving" sets the last day of membership, ends all group assignments
on that day and rejects open requests. Records are never deleted.

**Guardians:** You assign guardians explicitly per member — with contact
details, an optional user account, permitted actions and validity. One
account can represent several children and sees only those. Revoking ends the
access; the assignment is kept as a record.

**Who sees what:** The register is visible to people with the "View club
register" or "Manage club" permission. Group leaders only see members of
their own groups; a linked member sees their own record.

**Initial CSV import:** Use the import hub ("Club members") to read a legacy
list with preview and error list. The member number is the matching key: a
second run of the same file creates no duplicates but updates master data.
Type and joining date of existing members remain reserved for the history.
