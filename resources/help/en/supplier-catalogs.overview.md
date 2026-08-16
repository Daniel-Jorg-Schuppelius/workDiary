---
title: "Supplier catalogues"
topic: supplier-catalogs.overview
version: 1
audience: []
related:
    - articles.master
    - procurement.orders
---

Supplier catalogues keep your suppliers' price lists in the system —
separate from your own article master, but linkable to it.

**Catalogue sources:** One or more sources are created per supplier.
Supported formats are DATANORM, BMEcat and CSV with a freely assignable
column mapping (item number, name, purchase price, currency, GTIN,
manufacturer number, product group, availability, lead time). Files
arrive via upload or automatic remote fetch at a configurable interval;
an uploaded shopinfo.xml prefills mapping, character set and delimiter.
The mapping is stored on the source and reused for later fetches.

**DATANORM in detail:** Versions 4 and 5 are supported — besides article
files (DATANORM.nnn) also discount groups (DATANORM.RAB), product groups
(DATANORM.WRG) and price files (DATPREIS.nnn). List prices (price
indicator 1) are turned into net purchase prices via the discount group;
change files leave the stock untouched (processing mode selectable in
the import dialog). For customer-specific price files the K control
record is checked against the customer number stored on the source. The
character set is usually CP850. In the other direction the article list
exports your own master data as a DATANORM catalogue or DATPREIS price
file (also per B2B catalogue access with customer prices).

**Import:** Every run summarises how many catalogue items were newly
created, updated, changed in price or marked as discontinued. Catalogue
items carry tiered prices in addition to the purchase price.

**Linking (supply sources):** Catalogue items are linked to your own
articles (including variants) manually or via a GTIN/EAN suggestion.
Only this link establishes the supply source — the article master itself
is not touched by the import. Links can be removed at any time.

**Price reconciliation with approval:** If an import changes the purchase
price of a linked article, a calculation alert is created that has to be
reviewed and acknowledged. From the margin rules the system calculates
sales price suggestions directly on the catalogue item. Adoption into
the article never happens automatically: in direct mode the editor
applies it explicitly, in four-eyes mode an approval request is created
instead that a second person must approve or reject.

**OCI punchout:** Sources with configured shop access allow jumping
directly into the supplier's web shop. The basket filled there returns
via a time-limited, signed return link and is assigned to the selected
target warehouse — as the basis for further procurement.

Reading requires inventory view permissions; creating, importing and
linking require inventory posting permissions.
