---
title: "Communication notes"
topic: communication.notes
version: 3
audience: []
related:
    - diary-entries.edit
    - knowledge.articles
    - glossary.core
---

Communication notes document communication events outside the
system – phone calls, e-mails, on-site meetings, decisions – in a
structured way on the work order, customer or project.

Typical workflow:

1. Create the note in the modal: **type** (e.g. "Phone call",
   "E-mail", "On-site meeting", "Decision"), **direction**
   ("Inbound", "Outbound", "Internal"), **time** and **subject**
   (mandatory, 3–180 characters), content.
2. Optionally: **result/agreement**, **participants** (internal,
   customer, third party) and a **follow-up action** with deadline
   and responsible person.
3. Follow-up actions appear with their deadline on the dashboard and
   the work order page and are marked as completed there.

Visibility:

- Default is **"Internal"**; releasing a note as **"Customer
  visible"** (customer portal) is reserved for the org admin.
- **Confidential** notes are visible only to the creator and the org
  admin – any other access is recorded in the audit log.

Important rules:

- The time may lie at most a few minutes in the future – you document
  what happened, not what is planned.
- The creator may edit within 24 hours, afterwards only the org
  admin. **Deleting** (soft delete) is reserved for org admins.

Next steps: recurring solutions from phone calls are worth an article
in the **knowledge base**.

## Central note list

The **Notes** page in the sidebar lists all notes of the organization, newest
first – whether they are filed internally, with a customer, on a work order or
on a project. Filter by filing (internal or customer), customer, type and open
follow-ups; the search covers subject and content. Confidential notes of other
people only appear with the matching permission.

Add **tags** in the note dialog, several separated by commas. The list filters
by them, and the search also finds a note by its tag. The filter only offers
tags of notes you are allowed to see.

## Quick capture

A new note can be captured from the list and from the create menu on every
page:

1. Choose where to file it: **Internal** files the note with the organization,
   **Customer** with exactly one customer – it then also appears in that
   customer's record.
2. Choose the **type**, e.g. "General", "Phone call" or "Production". For a
   phone call, state whether it was inbound or outbound; general and
   production notes are always internal.
3. Enter subject and note text. Result, follow-up and confidentiality are
   under **More details**.

Filing a note with a customer does not publish anything in the customer
portal. Sharing remains a separate step in the customer record; internal
organization notes cannot be shared at all.
