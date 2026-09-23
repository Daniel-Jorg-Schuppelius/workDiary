---
title: "Riding operations: horses, lessons and assignment"
topic: club.horses
version: 1
audience: []
modules:
    - module.club
related:
    - club.resources
    - club.events
    - club.attendance
---

Riding operations add a lean **horse profile** to the club basics: name,
school or private horse (with owner), contact, riding groups, suitability
(“beginners”, “advanced” …), daily use limit and rest buffer. Horses are
neither members nor users nor stock items. Each horse is also a resource of
kind “horse”; booking, rest buffer, closures and suitability clearances run
through it.

**Riding lessons:** A riding lesson is a club event with riding leader, group,
hall/arena and seats. The leader assigns rider and horse per lesson; anyone
riding their own horse without a club profile is entered explicitly. Private
horses can only be assigned to their owner.

**Checks on assignment:** A horse is never assigned twice at the same time
(resource booking, rest buffer between two lessons). Blocked horses (closure,
e.g. lameness) cannot be assigned — this cannot be overridden. A missing
suitability clearance of the rider and the club’s daily use limit block the
assignment; the leader may override them **explicitly with a reason**, which is
logged. The system does not decide fitness for use and contains no invented
load limits.

**Horse unavailable:** A closure flags affected assignments for replanning and
informs the riding leaders of the lessons; nothing is deleted and there is no
automatic replacement. A replacement assignment goes through the same checks.

**Use record:** Horse use is recorded in minutes per horse and lesson —
separately from rider attendance in the attendance sheet. A horse change within
a lesson therefore never doubles training time. The horse page shows uses and
minutes of the month.

**Badges:** Badge events and evidenced prerequisites optionally use the exam
module; plain course participation does not need it.
