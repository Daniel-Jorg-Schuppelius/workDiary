---
title: "Supplier catalogues"
topic: supplier-catalogs.overview
version: 3
keywords:
    - DATANORM
    - BMEcat
    - import price list
    - wholesaler
    - catalog import
    - purchase prices
    - update prices
    - discount groups
    - OCI punchout
    - IDS Connect
    - Open Masterdata
    - supplier webshop
audience: []
modules:
    - module.lager
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

**Shop hand-off (OCI or IDS-Connect):** Sources with configured shop
access allow jumping directly into the supplier's web shop. You choose
the protocol on the source; IDS-Connect, as offered by electrical and
plumbing wholesalers, also needs your customer number at the wholesaler.
The basket filled there returns as a draft purchase order for the
selected target warehouse. Items whose supplier article number is linked
to an article are taken over; notes from the shop (such as delivery
times or blocked items) appear as a message. If the shop reports the
basket as already ordered, do not order it a second time. For IDS
sources, the shop icon in the item list opens the article page directly
in the shop.

**Open Masterdata:** A source in the “Open Masterdata” format does not
read a file but queries the wholesaler’s web service per article — by
wholesale number, GTIN or manufacturer and manufacturer number. The
lookup shows price, availability, images and documents; “Add to
catalogue” creates the catalogue item, which is then linked or taken
into the article master like any other. With a fetch interval, workDiary
regularly requests prices and availability of the listed articles,
“Refresh prices” does so immediately. Token URL, product URL, client ID
and login details are issued by the wholesaler; whether the customer
number is part of the login is stated in its access letter.

Reading requires inventory view permissions; creating, importing and
linking require inventory posting permissions.
