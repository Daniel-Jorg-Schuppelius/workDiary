---
title: "Planning tours"
topic: tours.manage
version: 3
keywords:
    - route planning
    - tour proposal
    - optimize route
    - route optimization
    - stops
    - dispatch
    - map view
    - visit order
    - driving route
    - customer visits
    - delivery run
    - trips to logbook
audience: []
modules:
    - module.planung
related:
    - contacts.manage
    - planning.shifts
    - travel-expenses.manage
---

Tours combine stops and assignments in a planned order. The map view
supports route review and optimization.

**Coordinates:** Orders need coordinates to be plannable. They are derived
from the order's address when you leave the address fields; without an
address of its own the customer address applies. Stops without coordinates
stay in the tour, at the end, and are marked as such.

**Proposal instead of automation:** "Compute proposal" determines an order
with arrival and departure times per stop. It takes into account driving
time, the orders' time windows, the working window from shift or
availability, mandatory breaks and qualifications. The proposal shows the
comparison with the current state, waiting times, breaks and orders that
cannot be scheduled, with a reason. Only "Adopt" writes order and times
into the tour; "Reject" requires a reason. Every proposal remains traceable
in the tour's history.

**Start time and starting point:** The tour's start time and starting point
(home, site or an address) determine the arrival times.

**Trips to the logbook:** Once a tour has been started, its legs can be
adopted as trips. Road kilometres come from the calculated route; without a
route the straight-line distance applies and the trip is marked
accordingly. For a vehicle in logbook mode the odometer reading is
requested and the readings of the trips follow on without gaps. Adoption is
possible once per tour.

**On the road:** The tour's driver view shows the next stop with navigation,
“Start tour”, per stop “Arrived”, “Done” or “Not met”, and “End tour”; the
times become the actuals of the stops. “Replan remainder” on a running tour
keeps completed stops and replans only the open ones from the last completed
stop.

**Plan and actual:** The report “Tours plan vs. actual” compares planned and
actual arrival and dwell time per stop, with deviations per driver and order
type; it provides experience values for the service duration of future
proposals.

Coordinate changes to active tours with the people involved.
