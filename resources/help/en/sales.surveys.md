---
title: "Surveys"
topic: sales.surveys
version: 2
keywords:
    - customer satisfaction
    - customer survey
    - satisfaction survey
    - NPS
    - Net Promoter Score
    - collect feedback
    - questionnaire
    - poll
    - anonymous survey
    - opt-out
audience: []
modules:
    - module.vertrieb
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

## Recording an objection

You set the opt-out on the customer: in the customer dialog under **Portal & miscellaneous**, tick **No surveys**. The invited person can also object themselves – every invitation sent to a customer and the survey page carry the link **Unsubscribe from surveys**. It leads to a confirmation prompt without login; after confirming, the box on the customer is ticked and the event is noted in the customer's change history. The link also works if the survey has already been answered or has expired. Invitations without a customer link carry no opt-out link. In the customer export and import the value is in the column `survey_opt_out`.

## Editing a questionnaire

**Edit** on the questionnaire page changes the title and the introduction
text. Anonymity can only be switched until the first invitation — after that it
stays as it was promised to the participants.
