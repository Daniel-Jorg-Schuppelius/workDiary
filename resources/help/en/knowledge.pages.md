---
title: "Knowledge pages"
topic: knowledge.pages
version: 1
keywords:
    - knowledge base
    - wiki
    - handbook
    - work instruction
    - required reading
    - read confirmation
    - onboarding
    - glossary
    - search gap
    - emergency handbook
    - picture guide
    - redact
    - OneNote
    - version
audience: []
modules:
    - module.knowledge
related:
    - knowledge.collections
    - knowledge.articles
    - work-queue.overview
---

**Knowledge pages** record how your organisation works: work instructions,
guides, decisions and the emergency handbook. You find them under **Daily
work** → **Knowledge & docs** → **Knowledge**, tab **Knowledge pages**.

## Structured like a notebook

- **Areas** are the notebooks (for example “Handbook” or “IT”). An area can be
  **restricted**: then only the selected roles, teams and people can read it.
- **Sections** structure an area, **pages** sit in them, **subpages** below
  (up to three levels).
- You write text in Markdown – with tables, images from the attachments
  (image address `anhang:filename.png`), **callouts** (`:::note`, `:::tip`,
  `:::warning`) and **collapsible sections** (`:::details Title`), each up to
  a line `:::`. Entered HTML is shown as text.

## Editing, versions and approval

1. **Edit** opens the text; every saved change is a new **version**. If
   someone saved the page in the meantime, saving reports a conflict –
   nothing is overwritten silently.
2. The **responsible person** **approves** the version. Until then readers
   keep seeing the last approved version.
3. Under **Versions** you compare two versions line by line and restore an
   older one as a new version.

## Keeping it current

Under **Responsibility and placement** you set the responsible person, a
**review interval** and a **valid-until date**. **Still correct** confirms
the page and sets the next review date. Due reviews, ending validity and
pending approvals appear in the **work queue**; after the validity date the
page counts as **outdated**.

## Required reading

Under **Required reading** you assign a page to all members, a role, a team
or a person – optionally as part of the **onboarding list**. Whoever must
read it confirms with **Read and understood**; a newly approved version
requires a new confirmation. The overview shows who confirmed which version.
Your own reading is under **My reading** and in the work queue.

## Finding

The search covers title and text – and the **glossary**: an abbreviation or
synonym also finds the term. Search terms without results go to
administration as **search gaps**, counted without your name. Pages also
appear in the global search. The **changed** marker shows pages that changed
since your last visit.

## Linking

With **Link** you attach a page to a customer, project, asset, order, ticket
or procedure. At the customer, project and asset it then appears in the
cockpit, at order, ticket and procedure in the **Knowledge pages** card. Like
other knowledge content, pages can be placed in **collections**.

## Picture guides

A **picture guide** consists of steps with a screenshot and text. In a step
you place **arrow**, **number**, **frame** and **text** markers.
**Redact** writes the chosen areas into the image; the unredacted original is
discarded and can no longer be retrieved, not even in older versions.
**Play** shows the guide step by step.

## Emergency handbook and PDF

Pages flagged for the **emergency handbook** make it up: **Emergency handbook
(PDF)** outputs all approved emergency pages with version, approval and
responsibility – so it is available even when the app is down. Every page can
be exported **as PDF** on its own.

## Trash and import

Deleted pages stay in the **trash** for 30 days and can be restored; after
that they are deleted permanently. Only authorised people may delete
permanently. **Import Markdown** takes over files as drafts; Obsidian folders
and OneNote notebooks are taken over via the import in the **Knowledge** hub
with the target **Knowledge page**.
