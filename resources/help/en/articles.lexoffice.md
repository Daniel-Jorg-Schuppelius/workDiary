---
title: "Lexoffice products & services"
topic: articles.lexoffice
version: 2
audience: []
modules:
    - module.vertrieb
related:
    - articles.master
    - invoices.manage
    - glossary.core
---

This page shows the directory of products and services synchronised from
Lexoffice. It is a read-only view of the ERP interface: the actual
maintenance happens in Lexoffice, and a pull sync keeps the local cache
up to date.

Each entry shows name, article number, type (product or service), unit,
net unit price and tax rate. You can search by text and filter and sort
by type and status (active, archived, all). The detail view opens an
entry's master data as a dialog.

With sufficient permission the sync can be triggered manually; it reports
how many entries were created, updated or archived. This requires
Lexoffice to be configured for the organisation.

The conflict strategy from the Lexoffice settings (Lexoffice wins, local
wins, manual review) also applies to the article sync: with “manual
review”, locally changed articles whose state differs in Lexoffice end up
as conflicts in the inventory conflict list (Inventory → Conflicts). There
you decide per article whether the local state stays, the Lexoffice state
is taken over or the conflict is dismissed. The manual sync reports the
number of new conflicts.
