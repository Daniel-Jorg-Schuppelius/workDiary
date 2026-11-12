---
title: "Equipment rental"
topic: rental.overview
version: 9
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
The contact person is pre-filled from the customer when you choose one and stays editable.

**Handover & return:** Separate reports document condition, accessories,
meter readings, photos and signature. The return carries the follow-up
decision: cleaning, repair/block or controlled escalation to claims.

**Report items:** The handover and return forms record a checklist, the
accessories, individual condition items and, on return, consumption in
addition to the overall condition. Check points and accessories come from the
rental profile; untick an accessory that is missing. The file shows the items
under “Show items”, and so does the customer portal, without internal remarks.

**Minimum condition:** In the rental profile you set the home site, the
minimum condition and the current condition. Every return takes the condition
from the report. If it is below the minimum, the device cannot be reserved or
used as a replacement until you adjust the condition in the profile after the
repair.

**Cleaning:** “Cleaning required” in the return report creates a cleaning
window; the device cannot be assigned during it. “Cleaning done” in the file
releases it before the window ends.

**Tentative bookings:** If another case holds a tentative booking in the
period, the file shows a notice with the cases affected. Reserving remains
possible.

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

## Direct booking with online prepayment

If **Prepayment for direct booking** is set under “Organization and settings”
(or a rental item has its own rate), a direct booking in the customer portal
leads to the payment page of the payment provider:

- The rental case is reserved immediately and holds the item until the
  **payment deadline**. Until then the booking shows **payment pending** under
  **Rental requests**.
- Once the payment arrives (provider notification or the customer’s return),
  the booking is **accepted**; only now do you receive the notification.
- Without payment by the deadline the booking **expires**, the rental case is
  cancelled and the item is free again. If a payment only arrives afterwards,
  WorkDiary confirms nothing but reports it to you for a refund or a new
  booking.
- The rental case shows the paid prepayment. When the rental items are
  transferred to the invoice, it is assigned to the invoice and covers it.
  Cancelling that invoice releases it again; the next invoice credits it.
- If an accounting program manages the invoices, the prepayment counts as
  credited once the rental items are handed over: the rental case and the
  transfer log show the amount to credit on the invoice there, and a later
  handover does not count it again.
- If WorkDiary keeps the books, the posting inbox books the prepayment on the
  payment date as an advance payment received, with VAT: the tax arises when
  the payment is received (minimum actual taxation), not only with the
  invoice. The WorkDiary invoice that credits it releases the advance payment
  — its receivable is only the open remainder, and the VAT of the advance
  payment is offset against the tax of the invoice, so that it arises only
  once. A cancellation document reverses the release; the advance payment is
  open again until the next invoice credits it. A refund after posting brings
  payment and invoice back into the posting inbox (reverse and post again).
  The account mapping needs the role "Advance payments received" for this
  (included in the SKR03 and SKR04 templates).

**Minimum condition at handover:** The rental profile’s minimum condition also applies at handover. If the condition has dropped since the reservation, WorkDiary refuses the handover until the device is repaired and the condition is updated in the profile.

## Waive a deposit

A requested deposit that has not been received can be **waived** with a reason. It then shows **Waived**; the reason is recorded in the rental's log.
