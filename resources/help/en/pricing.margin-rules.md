---
title: "Pricing & margin rules"
topic: pricing.margin-rules
version: 1
audience:
    - admin
modules:
    - module.lager
related:
    - supplier-catalogs.overview
    - articles.master
---

Margin rules derive sales price suggestions from purchase prices. They
ensure that prices from supplier catalogues do not have to be calculated
by hand — and that no adoption bypasses your calculation.

**Rule contents:** A rule calculates either with a markup percentage on
the purchase price or with a target margin as a percentage of the sales
price; if both are set, the target margin takes precedence. Optionally a
rule adds: a minimum margin (the suggestion is flagged if it would be
undercut), a minimum sales price and a rounding scheme for commercially
smooth end prices. Rules can be deactivated without removing them.

**Scope & order of application:** A rule applies globally, to one
supplier, to one product group or to the combination of both. If several
active rules match, the most specific one wins: supplier plus product
group before only one of the two criteria before global. On a tie, the
rule's priority decides, then the most recent rule. This lets you
maintain a company-wide default markup and override it selectively for
individual suppliers or product groups.

**Effect on catalogue adoptions:** The suggestions appear directly on
the linked catalogue items of your supplier catalogues. They never reach
the article's sales price automatically: in direct mode the editor
applies them explicitly, in four-eyes mode an approval request is
created instead. A request may only be approved by a person other than
the requester; rejections can carry a reason. The approval mode (direct
or four-eyes) is switched per organisation on this page, and open and
decided requests are visible there.

Completed transactions and historical prices remain untouched by rule
changes — a changed rule only affects the next price adoption. Reading
requires inventory view permissions; managing rules and requests
requires inventory configuration permissions.
