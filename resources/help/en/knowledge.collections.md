---
title: "Collections"
topic: knowledge.collections
version: 6
audience: []
related:
    - knowledge.articles
    - communication.notes
    - ideas.overview
    - documents.manage
    - learning.overview
---

A **collection** organizes content across modules: notes, idea maps,
knowledge articles, documents, courses and learning paths can sit together in
one collection. Belonging to a customer, work order or project stays leading —
the collection is the additional, freely chosen order for everything that does
not belong to a single case.

Typical workflow:

1. Create a collection from the **Knowledge** entry via **Manage
   collections**, optionally as a sub-collection
   of another one. Collections can be nested up to five levels deep and moved
   later.
2. On an item's detail page choose **Add to collection**. An item may sit in
   several collections; no copy is created.
3. **Archive** collections you no longer need instead of deleting them — the
   assignments are kept and can be restored.

**A collection grants no access.** Everyone only sees what they may see
anyway: other people's confidential notes, idea maps not shared with you,
confidential documents and learning content without the learning platform
module stay hidden — not even their number is shown. Only the creator sees a
**private** collection.

## The “Knowledge” entry

The **Knowledge** page lists notes, idea maps, knowledge articles, documents,
courses and learning paths in one list — or as tiles. The collection tree is on
the left (a collection includes its sub-collections); title and kind filter at
the top — title, kind and customer — and the tag badges narrow further.

**Knowledge** is the single door to the area: notes, knowledge base, idea maps
and documents hang above it as tabs, and collections are its management mode.
Every tab keeps its own columns and actions — deadlines and release stay with
the documents, article publishing stays in the knowledge base.

Switching keeps the filter: pick a customer and go to **Documents**, and you
see that customer's documents. Only what the tab can actually apply travels
along — a knowledge article belongs to no customer, so the choice stays
behind there.

Select several items and use **Add** to put them into a collection at once —
this also works in the **Search** results for notes, knowledge articles and
courses.

## Converting a note to a knowledge article

In a note's reading dialog, **Convert to knowledge article** creates a draft in
the knowledge base: the subject becomes the title, the text becomes the problem
description, and the tags come along. The article shows the note as its origin
under “Mentioned in”; a second click opens the existing article instead of
creating a new one. Confidential notes cannot be converted.

## Importing from Obsidian and OneNote

Administrators import existing notes **once or on demand** — read-only, with no
writing back and no ongoing sync. The **Knowledge** entry offers two buttons:

- **Import Obsidian** reads an Obsidian vault through an existing folder
  connection of the cloud document intake (Nextcloud, OneDrive, Dropbox, Google
  Drive). Enter the vault path relative to the connection's root folder.
  Sub-folders become collections, tags from the YAML header and `#tags` in the
  text come along, `[[wikilinks]]` become references. `.obsidian/` and
  `.trash/` are left out.
- **Import OneNote** only appears once the organisation has switched on
  **Allow OneNote import** in the Microsoft 365 plugin settings and used
  **Connect OneNote** in the Microsoft 365 panel. The connection requests the
  additional, read-only permission Notes.Read. The notebook becomes a
  collection, section groups and sections become sub-collections, each page a
  note or an article; the page content is imported as text.

Items are imported either as notes or as knowledge article drafts. Every
imported item shows its origin (“Imported from …”). A further run skips what is
already there and only imports new items — at most 300 new items per run.

## References and backlinks

On the detail pages of these items, the **References** card shows what an item
refers to and where it is mentioned:

- **Add reference** connects the item with a note, an idea map, a knowledge
  article, a document, a course or a learning path. Use the search field to
  narrow the list.
- **Mentioned in** lists everything that points to the page, grouped by kind —
  including knowledge base links and targets converted from or linked to idea
  nodes. Customers, projects and orders show this list as soon as something
  refers to them.
- **Remove reference** only removes references added by hand; knowledge base
  and idea map links are maintained there.

As with collections, a reference grants no access: a source only appears if
you may open it anyway.

Creating and filling collections and adding references requires the right
“Maintain collections and references”; viewing collections requires “See
collections”.
