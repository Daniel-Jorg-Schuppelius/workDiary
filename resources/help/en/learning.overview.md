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

A course can have **prerequisites** (all, or one is enough): they block the
start, not the assignment — mandatory enrolments are exempt. An **exam without
course** is a course of type “exam” with exactly one quiz unit; whoever passes
is credited with the configured target course — with the same feedback into
certificate, instruction record and qualification.

Coming from LearnDash, take over the **export ZIP** (course catalog → "LearnDash import"): courses, lessons, topics and quizzes are created as drafts, questions land in the catalog with their category. Images and media are not copied (placeholders to fill in), lesson videos only from allowed hosts. Completed courses are recorded as "imported" enrollments for people with a matching e-mail — without certificate and without instruction record, because an imported completion is no proof of its own. The dry run shows beforehand what would be created.

## Releasing freezes the content

Releasing creates a course version holding a snapshot of the entire content.
Ongoing participations stay on their version — the material does not change
under someone who is halfway through. After release the content is locked;
corrections go through a follow-up version.

If the course is linked to a training course, the release also writes the
course version there. The later record then carries the same version number.

Course options control the flow: a **release schedule** (days after enrollment
and/or a fixed date — the later one applies) locks a unit until that day at
every completion point — player, portal, external access and offline sync —
not only in the display. A **minimum dwell time** counts from first opening the
unit or via learning time. **Preview units** are readable in the portal without
enrollment (text only). **Categories** from the settings order the catalog;
**availability window** and **enrollment limit** apply to self-enrollment —
management may still assign, and mandatory enrollments bypass the limit.
Assignments carry **file rules** (extensions, count, size — never looser than
the system) and optionally an **auto-approve** with full points, which excludes
the four-eyes principle.

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

Questions live in the organisation's **question bank** (menu “Learning” →
“Question bank”) with category and short name; a quiz points to bank
questions and the same question may appear in several quizzes. In addition to
the fixed list, **draw rules** pick a number of random questions from a
category per attempt (“5 from fire safety”). Removing a question from a quiz
keeps it in the bank; only unused questions can be deleted — and a taken
attempt always keeps its own copy of the questions.

The quiz flow is configurable per quiz: all questions on one page or one
question per page, allow going back and skipping, required answers, result
texts per percentage range and a hint per question. Answers are saved as you
change them — nothing is lost after a connection drop; once the time limit has
passed only what was saved in time counts. The question overview shows
answered and flagged questions.

Graders can open the **attempt record** (questions of the frozen copy, given
answers, points, corrections) — every view is logged. Each quiz has
**statistics** (attempts, pass rate, time taken, error rate per question — rates
only from the minimum group onwards). From the participant list an **extra
attempt** can be granted despite attempt limit or waiting period, exactly once
and with a reason.

The **gradebook** per course shows learners × components. Without components it adds the points of quizzes and assignments; if trainers define **components** (quiz, assignment, manual grade) they can assign weights — all adding up to 100 or none at all. Manual grades are additive: a correction is a new entry, the latest counts. The **report card** (PDF) and the CSV export come from the same calculation; while something is still open the report card is marked as preliminary.

Refinements per question: answer options can carry **their own points** (“Label {3}”, negative allowed) — the chosen option then counts instead of all-or-nothing; a **self-assessment** is a scale without a correct answer, the chosen level is the score; an **essay** accepts text, a file or both — the file is available in grading. Per quiz, passing can additionally be required **in points**, and the subset per attempt can be set **as a percentage** of the available questions.

## Records

A passed course takes effect in exactly one place: certificate with
verification code, instruction record in the safety register, fulfilled
training obligation and extended qualification. No second record world is
created.

Certificates can be verified through a link. The verification page shows
course, date, validity and issuer — the name only abbreviated.

Learning data belongs to the person: the **data subject report** (data
protection module) lists enrollments, quiz attempts, certificates, learning
time and bookings as counts with a date range — never question texts or
answers. The **retention review** proposes finished enrollments without a
certificate for deletion once the regional period has passed (attempts and
learning time go with them); certificates stay longer because they serve as
proof and are then reduced to initials — the verification link keeps working.

## Who learns

Besides employees, customers can learn through the portal and external
participants without a user account. External people receive a time-limited
one-time link; their record is the same as an internal one.

The participants of a course are managed from the course record under
“Participants”: enrol people from the organisation or external persons, change
due date and access with a reason, cancel (never mandatory enrolments) and
create the access link for externals — a new link invalidates the old one. A
confirmed booking sends the link automatically.

The **learning platform settings** (course catalogue, management right) hold
the switch for points and leaderboard, the allowed embed hosts and the
**trainer view**: when enabled, people with authoring or grading rights see only
courses they own or are assigned to as trainers — catalogue, grading cockpit,
quiz statistics and course analytics follow the same rule. Management still
sees everything.

In the player every learner keeps **private notes** on a unit or the course —
visible only to themselves, not even to administrators, and absent from the
activity search; "My courses" collects them. A **question to the trainer** goes
to the responsible person and the course trainers: as a ticket with the
helpdesk module, otherwise by e-mail — both are notified as well. Two dashboard
tiles (hidden by default) show your own open courses and the grading backlog;
the activity search finds released courses — learners their own, authors all.

The **course catalog** can be shown as a list or as tiles (the choice is stored per person) and carries a **star rating** from course feedback — only from five answers, so nothing can be traced back to individuals. The portal additionally shows the **price** from the linked article. In the player, **focus mode** hides the sidebar; "Duplicate" creates a new draft from a course — learning material yes, enrollments and records no.

## Analytics and codetermination

Course analytics show rates and outliers, not personal profiles. Rates appear
from five enrolments onwards so that individuals cannot be inferred. Points,
badges and the leaderboard are off by default; the leaderboard additionally
shows only those who explicitly agree.

Notifications follow the organisation rules: assignment, due soon, overdue
(with escalation), submission received, grading available, certificate,
promotion from the waiting list, booking decision and learning-time
approval. The AI has three entry points — outline draft and question draft
in the editor, tutor in the player — and only suggests; adoption and grading
are done by hand. Points and badges appear under “My training”; the
leaderboard lists only people who opted in themselves.
