---
title: "Managing documents"
topic: documents.manage
version: 1
audience: []
related:
    - forms.fill
    - knowledge.articles
    - glossary.core
---

The documents module manages contracts, certificates, test reports,
manuals and more as **versioned files** with metadata, validity and a
reference to a customer, project, work order or asset.

Typical workflow:

1. **Upload a document**: title, document type (e.g. "Contract",
   "Certificate", "Test report", "Manual"), optionally validity
   (from/until) and a reference object. The file becomes version 1.
2. **Upload a new version** when the document changes – the version
   number increments, old versions remain untouched (with a version
   note).
3. **Download** either the current or an older version.
4. **Archive** when the document is no longer actively needed.

Important statuses: "Draft", "Active", "Archived" – **"Expired"** is
computed automatically from the "valid until" date and is not stored.
Expiring documents can be reported via notification rules.

Permissions: authorized staff may read and create documents available
to them. Editing is allowed for the creator or staff with extended
document permissions.

Risks: **deleting removes the document with all its versions**
(soft delete, only with delete permission). Versions themselves are
immutable – corrections are always made via a new version.
