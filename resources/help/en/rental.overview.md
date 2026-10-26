---
title: "Equipment rental"
topic: rental.overview
version: 3
keywords:
    - landlord
    - holiday apartment
    - tenancy
    - hire
    - tool hire
    - plant hire
    - machine rental
    - rental fleet
    - rental agreement
    - deposit
    - reserve equipment
    - equipment return
    - handover report
    - availability calendar
    - rental price list
    - telematics
audience: []
modules:
    - module.rental
related:
    - claims.overview
---

The module manages equipment and machine rental as traceable rental
files — from reservation and handover through runtime and return to
deposit and billing.

**Device pool:** A rental profile makes an asset rentable and carries the
device group, buffer times, accessories and the default rate card.

**Availability:** The calendar shows reservations, rental periods and
maintenance/cleaning/transport windows. Double bookings and blocked or
inspection-overdue devices are visibly prevented.

**Rental file:** Each case gets its own number (VER-…), customer, period
and responsibilities. The applied rate-card version is frozen as a
snapshot — later price changes never revalue old cases.

**Handover & return:** Separate reports document condition, accessories,
meter readings, photos and signature. The return carries the follow-up
decision: cleaning, repair/block or controlled escalation to claims.

**Billing:** Rental charges are released and invoiced locally or handed
over to the leading invoicing system. Deposits are separate financial
transactions — retention requires a mandatory reason.

**Site and geofence:** When equipment positions arrive via import (for
example from a telematics export), WorkDiary checks during a rental whether
the equipment is on site: at the rental's site with the radius set in the
settings, otherwise within the customer's geofences. If it leaves, the
responsible person and the team leads are notified.

## Apartments and holiday homes

The **Landlords** profile recommends the rental module. **Apartments & occupancy** manages long-term rentals, holiday rentals or both using the global header period. Vacant units remain visible.

Tenancies store the tenant, start, optional last rental day, monthly base rent, ancillary costs and deposit. Confirmed tenancies block the apartment through the last rental day, indefinitely without an end date. Set the end date at move-out; cancellation removes the entire occupancy.

Accepting an apartment holiday request immediately creates a firm reservation. Rate cards provide nightly prices as daily rates and optional final cleaning. Guest capacity, minimum stay and check-in times are checked. Publish the unit and grant the customer the rental requests capability for portal access. Monthly rents and deposits are administrative records in the MVP; rent and ancillary cost billing is not automatic.

The profile also brings three procedures (**Apartment handover**, **Move-out inspection**, **Holiday apartment changeover**), the **apartment handover protocol** with meter readings and keys, contract templates for residential leases with and without index-linked rent and for holiday apartment rental terms with deadlines such as the annual service charge statement, and custom fields for the apartment (living area, rooms, floor, pets, energy certificate). You confirm, decline or cancel a requested tenancy; a confirmed one can be cancelled. Cancelled and declined tenancies stay unchanged.
