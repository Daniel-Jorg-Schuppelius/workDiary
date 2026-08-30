---
title: "Learning platform"
topic: learning.overview
version: 1
audience: []
related:
    - training.overview
    - safety.overview
---

The learning platform answers **how people learn and are assessed**. *What*
someone owes by when stays in training management — the two interlock without
duplicating each other.

## Building courses

A course consists of sections and learning units. A unit is either content, a
quiz, an assignment, a classroom event or external material. Content is built
from blocks (text, callout, checklist, video, embed) — free-form HTML is
deliberately not available.

**Embeds require an allowed host.** The application's security policy would
otherwise block foreign pages silently inside the course, so the editor
rejects a non-allowed host visibly and immediately. Allowed hosts are
maintained in the settings.

## Releasing freezes the content

Releasing creates a course version holding a snapshot of the entire content.
Ongoing participations stay on their version — the material does not change
under someone who is halfway through. After release the content is locked;
corrections go through a follow-up version.

If the course is linked to a training course, the release also writes the
course version there. The later record then carries the same version number.

## Learning time is working time

Safety instruction must take place **during working hours** (section 12 (1)
ArbSchG). Every course therefore carries a time policy:

- **During working hours only** (default for mandatory courses): starting
  outside is refused.
- **Always counts as working time**: for instructed further training.
- **Outside only with approval**.
- **Voluntary, unpaid**: only for genuine extras — blocked for courses tied
  to mandatory training.

Learning time **inside** working hours is not counted twice; it is already
recorded through attendance. Learning time **outside** creates an attendance
span so that rest periods, maximum working hours and night work are checked.

## Quizzes

An attempt freezes the questions asked. If a question changes later, an old
result remains explainable — which is exactly what an auditor asks after an
incident. Attempts are never deleted; a correction is placed next to the
original value instead of replacing it.

Essays are graded by a human. The AI drafts courses and questions and answers
learner questions within the course context — **it must not grade or
decide**.

## Records

A passed course takes effect in exactly one place: certificate with
verification code, instruction record in the safety register, fulfilled
training obligation and extended qualification. No second record world is
created.

Certificates can be verified through a link. The verification page shows
course, date, validity and issuer — the name only abbreviated.

## Who learns

Besides employees, customers can learn through the portal and external
participants without a user account. External people receive a time-limited
one-time link; their record is the same as an internal one.

## Analytics and codetermination

Course analytics show rates and outliers, not personal profiles. Rates appear
from five enrolments onwards so that individuals cannot be inferred. Points,
badges and the leaderboard are off by default; the leaderboard additionally
shows only those who explicitly agree.
