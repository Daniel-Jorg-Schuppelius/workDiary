---
title: "Licence reconciliation"
topic: finance.reselling
version: 1
audience: []
modules:
    - module.finance
related:
    - finance.transfers
    - roles.buchhaltung
    - glossary.core
---

The **licence reselling reconciliation** checks whether every billing period
of the resold Microsoft 365 subscriptions is covered by an outgoing invoice
in Lexoffice, and compares sales prices with purchase prices.

**What you upload:** the Telekom Cloud Marketplace export (purchases.csv),
the contract export of the Quality Hosting partner portal (XLSX) and,
optionally, its price list. Both exports together form the stock before and
after the migration; successions are detected and the Telekom term is cut at
the Quality Hosting contract start.

**What the run does:** it splits every subscription into yearly or monthly
periods, maps each marketplace company to a Lexoffice contact (mapping file,
partner customer number, customer master data, unambiguous name search —
never guessing) and looks for a matching invoice line item in the window
around each period start.

**Status per period:** Covered, Below purchase (unit price below cost),
Partial, Amount only (voucher without a recognised product), Missing,
Unmapped. Resolve unmapped companies on the next run with a mapping file:
one line per company, `Company;Lexoffice contact UUID` or
`Company;customer:<Sqid>`.

**Price check:** per product you see the purchase price of the contracts,
the current list price and the manufacturer's RRP, plus the sales prices
per unit actually invoiced. A flag appears if your price is below purchase
or RRP, or if a running contract is more expensive than the current list.

The run reads Lexoffice in the background and takes a few minutes with many
customers. It writes nothing to Lexoffice and nothing to master data — the
report lives only on the run and can be downloaded as CSV.
