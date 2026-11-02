---
title: "Reports and prices"
topic: finance.resale.reports
version: 1
keywords:
    - margin report
    - margin
    - renewals
    - subscriptions without invoice
    - price check
    - RRP
    - export invoice proposal
audience: []
modules:
    - module.reselling
related:
    - finance.resale
    - finance.resale.periods
---

The register's **reports** compute over the due periods: margin report,
invoice proposal, renewals and subscriptions without invoice — each as CSV,
XLSX or PDF; the "Prices" page checks sales prices against purchase,
catalogue and RRP.

**Margin report:** Per product and per invoice recipient the due periods of
the range are shown with expected sale (quantity × sale price), billed (net
amounts of the invoice links, proposals included), expected purchase
(provider price × quantity) and actual purchase from the purchase entries.
Margin = billed − purchase; the actual purchase counts as soon as every
period of the row has one, otherwise the expected purchase. Amounts are
never summed across currencies — with several currencies there is one row
per currency and a notice. Export as CSV, XLSX or PDF; the invoice proposal
(open periods with open licence months and amount) as CSV or XLSX.

**Renewals and subscriptions without invoice:** The "Renewals" report shows
which subscriptions renew or end in the range (tiles 30, 60, 90 days):
renewal is the start of the next planned period, for cancelled
subscriptions the end counts. "Without invoice" lists subscriptions whose
oldest open due period is older than N days (default 60), with open periods
and open amount. Both as CSV or XLSX.

**Price check:** Per product the purchase per contract, catalogue price and
RRP from the last imported price list against the sale prices of the
subscriptions (minimum, median, maximum). Flags: "sale below purchase",
"sale below RRP", "contract above catalogue", "no sale price".
