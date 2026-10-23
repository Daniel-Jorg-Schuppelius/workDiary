---
title: "Search and activity research"
topic: search.overview
version: 4
keywords:
    - full-text search
    - global search
    - find entry
    - what was done for a customer
    - find past work
    - search operators
    - phrase search
    - exclude term
    - typo tolerance
    - search synonyms
    - filter by tags
    - fuzzy search
audience: []
related: []
---

The search answers one question above all: **What was done, when and for which
customer?** It searches time entries (including remote-session notes and
timesheet lines), jobs with comments, timesheet notes, tickets, protocols, open
items, communication notes and knowledge articles — each with project, end
customer and customer as context. Below that come master data and records:
customers, suppliers, leads, projects, tasks, contracts, objects, invoices,
incoming invoices, purchase orders, expenses, articles, people, documents,
forms and attachments — each only with the module enabled and read access.
Plugin data joins them: vouchers from accounting programs and shop orders from
Etsy and Billbee (for administrators); imported tasks name their plugin, such
as "GitHub Issues".

"All results in this group" shows one group on its own with up to 200 results.
Period, person and customer apply there as well: invoices filter by invoice
date, tasks by assignee, leads by the person responsible. If a group does not
know the selected person or customer filter — suppliers or articles, for
example — it is left out instead of appearing unfiltered.

## How searching works

- All words must occur, no matter where: "smtp exchange" finds the time entry
  "Switched send connector to SMTP" in the project "Exchange migration".
- Words are matched at their start: "exch" finds "Exchange" and "Exchangeserver".
- Words in quotes ("smtp relay") must appear right next to each other.
- A minus excludes: "printer -toner".
- Filler words such as "when did we … do" are ignored.
- If a word occurs nowhere, the search also looks for similarly spelled words
  and says so. "Similar spellings" includes variants for known words too —
  helpful for typos in the notes themselves.
- Administrators maintain synonyms under System › Organisation › Search
  synonyms.

## Overview and filters

"Customers & end customers" shows where there are results and in which period;
one click filters on it. The filter bar offers source, period, person, customer
(including its end customers), end customer and sort order. Without a search
term but with a customer, end customer or project, the latest activities are
shown.

"Tags in the results" counts the tags of the entries found; one click narrows to
a tag, the cross on the filter removes it again. If you may see collections, the
filter bar also offers **Collection** — it includes its sub-collections. Both
filters work without a search term too. Only entries you may open are counted,
so tags of other people's confidential content do not appear.

## Entry points

Customer page, end-customer page and project have their own search field or
button. A recognised caller opens the search directly with their customer
filter. With the AI module active, "AI answer" summarises the results.

The result list respects module and permission boundaries — only items your
role may open are shown.
