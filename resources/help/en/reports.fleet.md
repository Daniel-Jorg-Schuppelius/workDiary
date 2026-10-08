---
title: "Fleet, logbook and driving times"
topic: reports.fleet
version: 1
keywords:
    - vehicle report
    - odometer reading
    - fuel costs
    - cost per kilometre
    - tax logbook
    - private trips
    - taxable benefit
    - 1 percent rule
    - company car
    - driving times
    - rest periods
    - driving break
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
    - buchhaltung
    - user
    - aussendienst
related:
    - assets.fleet
    - travel-expenses.manage
    - fleet.license-checks
    - reports.arbzg-compliance
    - admin.organization-settings
    - reports.overview
---

These reports concern vehicles and trips: kilometres and energy costs per
vehicle, the tax logbook of a vehicle, the comparison of the logbook method
with the 1% rule, and the evidence of driving and rest times. They are based
on the trips in the **Logbook** (**Trips & expenses** → **Logbook**), the
receipts in the **Refuel & charge log** (**Fleet** → **Refuel & charge log**)
and the vehicle data under **Fleet** → **Vehicles**.

## Period and export

- You choose the period with the period selector in the header. The 1%
  comparison uses a calendar year instead.
- **PDF** downloads a print version; **CSV** and **Excel** are available under
  **Export**. Exports keep the selected filters; PDF and CSV exports are
  recorded in the audit log.

## Fleet

**Reports** → **Resources** → **Fleet** opens the **Fleet report** with trips,
refuels, energy costs and reimbursements per vehicle.

- Tiles: **Vehicles**, **Σ km** (with the number of trips), **Refuels /
  charges** (with litres and kWh), **Energy costs** (with the total of the
  reimbursements) and **Ø €/km**.
- Charts: **Kilometres per vehicle (top 15)** and the kilometres over time.
- Table per vehicle: **Vehicle**, **Powertrain**, **Trips**, **km**,
  **Reimbursement**, **Refuels**, **Litres**, **kWh**, **Energy costs**,
  **€/km** and **Odometer reading**, with a totals row.

This is how the values are formed:

- **Trips**, **km** and **Reimbursement** come from the trips in the logbook
  that have a vehicle assigned and whose date lies in the period.
- **Refuels**, **Litres**, **kWh** and **Energy costs** come from the fuel and
  charging receipts that started in the period.
- **€/km** divides the energy costs by the kilometres; without kilometres or
  without costs the field stays empty.
- **Odometer reading** is the last mileage from the fuel and charging receipts
  of the period, otherwise the reading stored with the vehicle.

Filters: **Area** (**Only my trips** or **Entire fleet**, administrators only)
and **Employee**. Everyone else only sees their own trips and receipts. Export
as PDF, CSV and Excel.

## Logbook report

**Reports** → **Resources** → **Logbook report** shows the tax logbook of a
vehicle: odometer readings, trip type, destination, purpose and driver, totals
per trip type and the private share.

- Choose the **Vehicle**. Vehicles in **Logbook mode** are listed first and
  marked accordingly. Without administrator rights the list contains vehicles
  without a **Default driver** and the vehicles whose default driver you are;
  administrators see all vehicles.
- If the vehicle is not in logbook mode, a notice points out that trips
  without odometer readings and without locking are not a logbook for tax
  purposes. You switch the mode on at the vehicle with **Logbook mode (tax)**.
- Tiles: **Trips** (with the number of locked ones), **Σ km**, the kilometres
  per trip type (**Business**, **Commute**, **Private**) and **Private share**
  (private kilometres in relation to all kilometres).
- Table: **Date**, **Start km**, **End km**, **km**, **Trip type**,
  **Destination**, **Purpose**, **Driver** and **Status** (**locked**,
  **open** or **reversed**, plus **signed** and **Cancellation trip**). The
  footer shows the kilometres per trip type.

The kilometres of a trip are end minus start km; if the readings are missing,
the recorded distance counts, twice for a round trip. The list contains all
trips of the vehicle in the period, including those of other drivers.
Reversed original trips remain visible struck through, but do not count in
any total.

Export as PDF, CSV and Excel as soon as a vehicle is selected. CSV and Excel
additionally contain the start address, the times of locking and signing, the
reversal flag, the corrected trip with the correction reason, as well as the
totals and the private share.

## 1% comparison

You open the **1% comparison** with the button of the same name on the
**Logbook report** page; there is no menu entry of its own. For each vehicle
it compares the taxable benefit under the logbook method with the 1% rule. It
is a simplified calculation and not tax advice.

- **Year**: the current year and the six previous years; the previous year is
  preselected.
- Only vehicles in logbook mode are listed, with the same vehicle selection
  as in the logbook report.
- **months**: months with trips. **Total km**, **of which private** and **of
  which commuting** only count trips with start and end km; reversed original
  trips do not count.
- **Total costs**: energy costs from the fuel and charging receipts of the
  year plus other annual costs; the tooltip shows both parts.
- **Logbook method**: total costs times the share of private and commuting
  kilometres in all kilometres.
- **1% rule**: **Gross list price (€)**, rounded down to the full €100, of
  which 1% per month of use plus 0.03% per kilometre of **Home–work distance
  (km)** and month. For electric and qualifying hybrid vehicles acquired from
  2019 onwards, the basis drops to a quarter or a half depending on the
  **Acquisition date** and the list price; the cell then shows **Basis** with
  0.25% or 0.5% instead of 1%. Without a list price it shows **List price missing**.
- **Cheaper** marks the method with the lower value.

The euro icon opens the **Other annual costs** dialog for leasing, insurance,
vehicle tax, maintenance, repairs and depreciation, with **Amount (€)** and
**Note**. This requires the **Manage vehicles** permission. The page offers no
export.

## Driving time evidence

The **Driving time evidence** is a download of the driving and rest times per
driver and calendar day. You find it on the **Working-time compliance** page
(**Reports** → **Finance & audit**) in the **Export** menu. It only appears if
the driving time rules are switched on in the organization's compliance
settings under **Driving and rest times**, and it requires the **View
working-time compliance** permission.

- It evaluates the effective trips with departure and arrival times on
  vehicles for which **Apply driving and rest time rules** is set. Tachograph
  data is not read.
- Columns: **Driver**, **Personnel number**, **Date**, **Vehicles**, **First
  departure**, **Last arrival**, **Driving time**, **Longest driving stint
  without break**, **Breaks (min)**, **Rest before** and the day's
  **Findings**.
- The download takes over the period and the employee filter of the page and
  delivers a CSV file. It is recorded in the audit log.

The findings are based on the limits of Regulation (EC) 561/2006 and the
FPersV: at most 9 h of driving per day (10 h twice a week), 56 h per week and
90 h per fortnight, a 45-minute break after 4.5 h (splittable into 15 and 30
minutes), 11 h of daily rest (at most three times a week 9 h) and 45 h of
weekly rest (24 h with compensation). This is not legal advice; which
regulations apply in the individual case is for the business to clarify.
