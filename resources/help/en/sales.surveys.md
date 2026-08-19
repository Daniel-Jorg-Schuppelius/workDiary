---
title: "Surveys"
topic: sales.surveys
version: 1
audience: []
related:
    - contacts.manage
---

A lean **survey engine** for NPS and free questionnaires — no marketing
automation. Question types: **NPS (0–10)**, scale (1–5), choice, free text.
Participation runs via a **one-time link** (valid 30 days), without portal
login.

## Three mandatory rules

- **Fatigue guard:** at most one invitation per email address in 90 days —
  across **all** questionnaires. The automatic trigger skips silently, manual
  sending is rejected with an error.
- **Opt-out per customer:** whoever objected is not invited any more.
- **Anonymity is a storage property:** with anonymous questionnaires the
  answer carries no invitation reference and the invitation no response
  time — a re-identification join has no fields. That is why the setting can
  no longer be changed after the first invitation.

## Triggers

Manually per customer — or automatically **after ticket close** (enabled on
the questionnaire). A failed invitation attempt never prevents the ticket
status change.

## Evaluation

**NPS score** = %promoters (9–10) − %detractors (0–6). Without answers there
is no score — no value means “nothing to compute”, not zero. The ticket CSAT
(portal rating) remains independent.
