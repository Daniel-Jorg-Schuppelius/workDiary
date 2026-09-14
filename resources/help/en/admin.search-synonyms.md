---
title: "Search synonyms"
topic: admin.search-synonyms
version: 1
audience: []
related: []
---

Synonym groups connect terms with the same meaning for the search of the whole
organisation. If "smtp, mailrelay, sendeconnector" form a group, a search for
"smtp" also finds entries that only mention "Sendeconnector".

- One term per line, at least two, at most 20. Multi-word terms such as
  "send connector" are allowed.
- Upper/lower case, umlauts (ä = ae) and hyphens do not matter.
- "Import IT template" creates typical IT groups and skips every group of which
  one term is already maintained.
- Deactivated groups are kept but have no effect.

The search page shows directly above the results which synonyms a search
included.
