---
title: "AI services"
topic: ai.services
version: 6
keywords:
    - masking
    - DPA
    - data protection approval
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

**Choosing a connection:** If your organisation allows “users may choose”
for a service and several connections are permitted, the suggestion shows a
selection. “Regenerate” creates the suggestion with the chosen connection;
the previous open suggestion is replaced. The choice is recorded in the audit
log.

**Connection choice:** If the organisation allows “Users may choose” for a service, a suggestion can also be regenerated with another permitted connection at the transfer (line texts), in the activity research of the search and for “Explain plan vs. actual”.

**Monthly budget:** On the **AI usage** page, “Monthly budget” sets an upper
limit per calendar month for each family – tokens for language models,
characters for translations. Once it is reached, workDiary rejects further
calls of this family until the end of the month with a message; the tiles show
the utilisation. If empty, the operator default applies; without a default,
usage is unlimited.

**Masking:** Before every cloud connection, workDiary replaces IBANs, VAT IDs,
tax numbers, tax identification numbers, email addresses and phone numbers with
placeholders such as `[IBAN-1]` or `[EMAIL-2]`. Within one request the same
value always gets the same placeholder. In drafts, summaries, translations and
extracted field values workDiary puts the values back before they are shown;
the provider never sees them. Only values that pass validation are detected
(check digits for IBANs, VAT IDs and tax IDs) – amounts and dates stay
untouched. Customer names from your own records are masked as before. Local
connections see the values in plain text as long as “Also mask before local
connections” is off under **Data protection**. The audit log counts the
masked values per kind without naming them.

**Data protection approval:** For each connection, “Data protection approval”
records that a data processing agreement is in place (for cloud services with
the document), that processing takes place in the EU and that the provider
does not train on your data – together with the date and the reviewer. For a
local connection the agreement is only needed if a service provider runs the
model. The approval is valid for as many months as set under **Data
protection** (default 12); 30 days before it expires, the work queue reminds
you. Approval and revocation appear in the audit log. Approvals are granted by
anyone with the right “Grant and revoke data protection approval of AI
connections”.

**Approved connections only:** If this switch under **Data protection** is on,
no AI function uses a connection without a valid approval; the preflight and
the error message state the reason. New organizations start with the switch
on. In organizations that existed before this version it stays off until an
admin switches it on – until then the open approvals appear as tasks in the
work queue.
