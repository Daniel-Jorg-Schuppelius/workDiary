---
title: "Agile project management"
topic: agile.overview
version: 4
keywords:
    - Kanban
    - Scrum
    - sprint planning
    - backlog
    - story points
    - burndown chart
    - velocity
    - task board
    - Kanban board
    - user stories
    - acceptance criteria
audience: []
modules:
    - module.agile_projects
related:
    - projects.manage
    - work.overview
---

An agile board can be enabled for every project — either for continuous work
(Kanban) or for sprints (Scrum). The guiding principle: the task remains the
leading record. Every card on the board is a regular task with assignees,
time bookings and links; the agile module adds rank, story points and sprint
assignment, but replaces nothing. Story points are deliberately never
converted into time or money — booked time and points sit side by side on
the card, kept separate.

**Product backlog:** One ranked list of all work items per project. New items
are created directly in the backlog or by adopting existing tasks. Each item
carries a type (such as story or bug), story points, acceptance criteria and,
if needed, a blocked marker. The order is maintained by moving items up and
down; filters by type, blockers and search term keep even large backlogs
manageable.

**Acceptance criteria:** The list icon in an item's row opens its criteria:
add, tick off one by one, remove. The number next to it shows fulfilled and
existing criteria; before a move into a done column, the board names
unfulfilled criteria.

**Definition of done:** The board settings hold the project's definition of
done, one line per item. When a card is moved into a done column, the board
opens a dialog with these items and the acceptance criteria that are still
open. The card only moves once every item is confirmed. Users with the
override permission can move it despite open points and must give a reason;
the same applies to a WIP limit that has been reached. Every override
appears in the history and counts in the quality series of the reports,
which also shows the DoD deviation rate per week.

**Board:** Columns represent the workflow; cards move from left to right.
With a sprint selected, the board shows only the items of that sprint.

**Sprints:** Planning by assigning backlog items, then start, completion or
cancellation. On completion, every unfinished item requires an explicit
decision — back to the backlog or into the next sprint. Nothing is carried
over silently.

**Conflict protection:** Rank changes, board settings and sprint actions are
protected against parallel editing: the first save wins, the second person
receives a notice and continues from the current state. There is no silent
overwriting.

**History:** Every relevant change — rank, column, sprint assignment,
points — is recorded as an agile event and can be traced as a history.

**Reports:** The burndown shows the daily series of remaining points for a
running sprint; velocity shows the completed points per finished sprint of a
board. A management overview summarises all boards: active sprint, velocity
median, unplanned work, blockers and an empirical forecast — the latter only
appears once enough comparable weeks exist, instead of feigning precision.
The overview shows only projects the viewer is allowed to see anyway.

**Done outside the board:** If a card’s task is completed elsewhere (task list, import), the card follows into the done column. Open acceptance criteria and the unconfirmed definition of done are then recorded as a deviation in the board history and count towards the DoD deviation rate.
