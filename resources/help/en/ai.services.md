---
title: "AI services"
topic: ai.services
version: 1
audience: []
related:
    - invoices.manage
    - quotes.overview
---

AI assistance is optional and off by default. Under
**Administration → AI services** you connect providers (cloud or local,
e.g. Ollama), enable individual capabilities and define per capability
which connections are allowed and which one is the default.

**Privacy:** The data-flow preview shows per capability which data
classes are sent to which provider. Cloud connections are blocked at
high sensitivity and in the outpatient-care industry profile; plans
that use inputs for training cannot be connected. API keys are stored
encrypted and never displayed.

**AI memory:** Glossary terms, style rules and example pairs per
organization, customer or capability improve suggestions — without
training third-party models. Learning happens only after your
confirmation ("Remember?" dialog).

**Item texts:** In invoice and quote drafts the AI creates text
suggestions per item (including translations). Nothing is applied
until you click — quantities, prices and tax remain untouched.
