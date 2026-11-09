---
title: "AI services"
topic: ai.services
version: 3
keywords:
    - AI assistant
    - artificial intelligence
    - LLM
    - language model
    - Ollama
    - text suggestions
    - translate line items
    - rewrite text
    - glossary
    - enable AI
    - AI provider
audience: []
modules:
    - module.ai
related:
    - invoices.manage
    - quotes.overview
---

AI assistance is optional and off by default. Under
**Administration → AI services** you connect providers (cloud or local,
e.g. Ollama), enable individual capabilities and define per capability
which connections are allowed and which one is the default.

**Who may use AI:** Suggestions, translations and bulk actions are only
available to people with the permission “Request AI suggestions”.
It is part of every standard role for employees — from management,
accounting and HR to office staff, field service and call centre — but not
of the customer portal and not of the read-only support role. For custom
roles you grant it under **Roles & permissions**. Only those with “Manage AI
provider connections and capability routing” maintain
connections, approvals and budget.

**Privacy:** The data-flow preview shows per capability which data
classes are sent to which provider. Cloud connections are blocked at
high sensitivity and in the outpatient-care industry profile; plans
that use inputs for training cannot be connected. API keys are stored
encrypted and never displayed.

**Replace a key:** If an API key expires or is compromised, open
“Edit connection” and enter it under “New API key”. If left empty,
the current key is kept. The new key is checked straight away; the
connection stays in place and its assignment as default per capability is
not lost.

**AI memory:** Glossary terms, style rules and example pairs per
organization, customer or capability improve suggestions — without
training third-party models. Learning happens only after your
confirmation ("Remember?" dialog).

**Item texts:** In invoice and quote drafts the AI creates text
suggestions per item (including translations). Nothing is applied
until you click — quantities, prices and tax remain untouched.

**Bulk actions:** On a draft invoice, “Translate all” translates every line,
and in a protocol “Refine all items” rephrases every item with text. Both run
in the background. Each line and item receives its own suggestion, which you
accept or reject individually; nothing is applied automatically.
