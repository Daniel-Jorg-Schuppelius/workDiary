---
title: "Teams, match days and lineups"
topic: club.matches
version: 1
audience: []
modules:
    - module.club
related:
    - club.groups
    - club.events
    - club.attendance
---

Team and racket sports (football, handball, basketball, volleyball, hockey,
table tennis, tennis …) build on groups, events and attendance. A sport is a
**sport profile**, configuration rather than a special case in the system:
sport family, positions, squad sizes (field/bench), result format (goals,
points per period, sets), singles/doubles, age-class cut-off date, disciplines
and resource types. The club adapts profiles or adds more; association rules
are not hard-coded.

**Teams:** A team is a group flagged as “team” with a sport profile (its own or
the department’s). The age class is a free label (e.g. U15); age criteria of a
team are checked on the profile’s cut-off date within the season, not on the
calendar day.

**Seasons and squads:** Seasons are named periods (e.g. 2026/27). Per team and
season there is a squad with validity per person, jersey number, position and,
in racket sports, the manually maintained strength order. Previous seasons stay
unchanged. **Guest players** from a partner club are kept in the squad with
their club of origin; they are persons of kind “guest” without fee assignment,
login or group membership.

**Match days:** A match day is a club event with sport details: team, opponent
(no customer or user record), competition, home/away, venue, meeting time and
leader. The team is the event’s target group; further groups can be added.

**Availability and lineup:** Members answer available, unavailable or “maybe”
in the portal — an answer is not a nomination. The leader sets the lineup
(field/bench with position and jersey number; in racket sports singles and
doubles pairings) and releases it. Squad sizes and positions come from the
profile. A person in singles and doubles stays one person. Before release,
conflicts are shown: a simultaneous assignment in another lineup or an explicit
“unavailable”. Releasing despite conflicts needs a reason and is logged.
Nominated players become participants of the event; actual attendance is
recorded separately in the attendance sheet.

**Event roles:** Referee, timekeeper/jury, driver, venue duty or range officer
are assigned per event — to a member, a staff user or as an external name. A
member with a role counts as club participation, not as a squad place.

**Result:** The result is entered manually in the profile’s format (goals,
points per period with totals, sets with set scores). Scorers and remarks go
into the note. Changes are logged; there is no automatic table from incomplete
results.

**Fixture import:** CSV or ICS produce a **proposal list** that creates nothing
before confirmation. The leader checks opponent, venue and time and accepts or
dismisses each proposal; known rows are skipped on re-import, possible
duplicates of existing match days are flagged. CSV columns (header row, any
order): date, time, optional end, opponent and home/away — or home and away as
team names — plus venue and competition. Association synchronisation is not
included.

## Starter packages per sport

On the **Sports** page a **starter package** creates a sport in one step: sport
profile, department, typical groups or teams and facilities, plus, depending on the
sport, a grading system (martial arts), school horses (riding) or an attendance
requirement (shooting). Eleven sports are included: martial arts, table tennis,
hockey, riding, football, handball, basketball, volleyball, tennis, athletics and
shooting. A package is configuration, not a special case: everything it creates can
be changed or deleted afterwards, existing entries of the same name stay untouched,
and no association rules or legal thresholds are built in.

The demo industry **Sports club** installs all eleven packages and fills them with
fictitious people, events, attendances, fees, match days, competitions, exams and
riding lessons.
