---
title: "Test equipment & calibration"
topic: asset-compliance.overview
version: 2
keywords:
    - test equipment management
    - gauge management
    - calibration certificate
    - measuring instruments
    - inspection due dates
    - inspection records
    - DGUV V3
    - electrical safety test
    - vehicle inspection
    - equipment lockout
    - ISO 17025
audience: []
modules:
    - module.asset_compliance
related:
    - rental.overview
    - asset-finance.overview
---

The module manages inspection-relevant measuring devices, machines,
vehicles and installations: verification, calibration, DGUV/UVV, vehicle
inspection, electrical testing, manufacturer service and internal checks
— with evidence and usage blocks.

**Inspection profiles (catalogue):** global templates are overridden by
organisation profiles with the same code. Profiles carry interval,
warning lead time, tolerance, grace period and blocking mode — rule
changes are data maintenance, not a release.

**Inspection duties:** assigning a profile to an asset creates a duty
with due date and responsibility. Due inspections raise warnings; after
the grace period the system blocks via the shared blocking model —
rental, dispatch and usage read the same status.

**Reports & certificates:** inspections record measured values against
frozen limits, result, validity, signature and an optional calibration
certificate. Evidence is immutable — corrections are versioned.

**Exception releases** are time-limited, justified and audited per usage
context. **External inspectors** deliver evidence via a limited,
purpose-bound access. The **norm reference matrix** links inspection
kinds to legal sources without any conformity promise.

## Inspection calendar

Menu **Test equipment** → **Inspection calendar**; the page is also available
as a tab on the other test equipment pages. The list shows the open
inspection appointments (**Planned**, **Announced**, **In progress**) sorted
by due date. The status filter also shows completed, missed or cancelled
appointments. Columns: **Due** (with the planned date, if any), **Asset**,
**Inspection profile**, **Inspector / inspection body** and **Status**.

**Schedule inspection appointment** (at the bottom of the page): choose the
**Inspection obligation** – the list shows asset, profile and next due date –,
**Due on** (required), optionally **Planned on**, **Internal inspector** or
**External inspection body**, then **Schedule appointment**. A new appointment
starts as **Planned**. For appointments with an external inspection body,
**Invite access** invites the body via time-limited access.

**Record inspection** is available for open appointments and opens the
inspection record:

- **Result**: **Passed**, **Passed with restrictions** or **Failed**.
- **Performed on (empty = now)** and **Valid until (empty = interval)**:
  without a value, the record is valid for one inspection interval from the
  date performed. A failed inspection gets no valid-until date.
- One measured value per requirement of the profile; the limits are shown
  next to it.
- **Follow-up decision**: **None / approval**, **Recalibration (blocking)**,
  **Repair (blocking)**, **Restricted use** (only noted, does not block),
  **Block**, **Decommissioning** (also blocks) or **Open claim** (creates a
  claim if your organisation uses the claims and warranty module), plus
  **Justification of the measure**.
- **Certificate / inspection record** (expandable): certificate number,
  issuer (required as soon as a number is entered), issue date, validity,
  measuring range, tolerance and a document whose hash is stored.
- **Signature (name)**, **Inspection cost (net, €)** and **Remark**.

**Document review** creates an immutable record and closes the appointment as
**Completed**. A passed inspection sets the next due date to one inspection
interval after the date performed. Passed without a follow-up measure lifts
blocks caused by overdue or failed inspections; “Failed” without a chosen
measure blocks the asset. If the profile requires a certificate, a passed
inspection without a certificate number is rejected.

**Permission:** viewing with **List inspection duties and test equipment**;
scheduling appointments with **Maintain inspection profiles and duties**;
recording inspections with **Perform inspections and record evidence**.
**Invite access** requires one of the last two permissions.

## Inspection orders

Menu **Test equipment** → **Inspection orders**. Here you award due
inspections to an inspection provider; the provider does not need a user
account. The list shows **Title**, **Inspection provider**, the number of
**Items**, **Status** and **Offer price**; **Open** leads to the order.

This is how an order runs:

1. **Create inspection order**: choose **Title**, **Inspection provider**
   (selected from your suppliers), **Provider email** and at least one
   inspection appointment with the status **Planned** or **Announced**, then
   **Send order**. The provider receives an e-mail with a link that is valid
   for 90 days. The selected appointments change to **Announced**, the order
   is **Requested**.
2. Via the link the provider submits an offer with price, planned date and a
   note; the order is then **Offer received**. In the order view you choose
   **Accept offer** (status **Commissioned**) or **Reject offer** (back to
   **Requested**; the provider can submit a new offer).
3. Once commissioned, the provider reports result, inspection date, validity,
   certificate number and remark for each item, optionally with the
   certificate as a file. The order is then **Results reported**.
4. **Take over results** creates an inspection record for every reported
   item – like an inspection in the inspection calendar, with the provider as
   inspector and issuer and the certificate including its checksum. The order
   is then **Completed**; the table shows “taken over” for the items.

**Cancel order** is possible as long as no results have been reported; the
announced appointments become **Planned** again. If a profile requires a
certificate and a passed result has no certificate number, the takeover stops
with a message.

**Permission:** viewing the list and the order with **List inspection duties
and test equipment**; creating, deciding on the offer and cancelling with
**Maintain inspection profiles and duties**; taking over results with
**Perform inspections and record evidence**.

## Inspection rounds

Tab **Inspection rounds**, for example in the **Inspection calendar**. An
inspection round is a target list of due inspections for a location or a
group that you work through on site by scanning. The list shows **Name**,
**Due until**, **Done** (completed out of all inspections) and **Status**
(**Open** or **Closed**).

**Create round**: **Name**, **Due until** (prefilled with today plus 30 days)
and optionally **Location**, **Group (category)**, **Inspection profile** and
**Customer**. The round takes over all active inspection obligations due by
that date; decommissioned assets are left out. Obligations that fall due
later are not added. If nothing is due for the selection, no round is
created.

In the round you see the figures **Done**, **Missing** and **Overdue** and
all items with asset, inspection profile, due date and state (**Open**,
**Overdue** or the recorded result).

- **Scan object**: enter or scan a QR code, asset no., inventory no. or
  serial number and choose **Open**. On devices with NFC support **Read NFC
  tag** appears as well. If exactly one inspection is open for the object,
  the capture opens; if there are several, you choose the inspection profile.
- **Record inspection** (quick capture): **Result**, **Remark** and
  **Signature (name)** (prefilled with your name), then **Save inspection**.
  This creates the same immutable record as in the inspection calendar, just
  without measured values and certificate. “Failed” blocks the asset. If the
  profile requires a certificate, record a passed inspection in the
  inspection calendar – the capture points this out.
- **Close round**: inspections that are still open remain as missing; after
  that no more captures are possible in the round.

**Permission:** viewing with **List inspection duties and test equipment**;
creating, scanning, capturing and closing rounds with **Perform inspections
and record evidence**.

## Inspector tour

In the **Inspection calendar** via the **Inspector tour** button. This lets
you plan the open inspection appointments of an internal inspector as a tour.

- At the top you choose the **Inspector** (prefilled: yourself) and **Due
  by** (prefilled: today plus 14 days).
- The table shows the open appointments for which this person is entered as
  internal inspector and which are not yet assigned to a job, with due date,
  asset, inspection profile and **Location**. All appointments are
  preselected.
- With **Tour date** (prefilled: tomorrow) and **Plan tour**, every selected
  appointment becomes a job for the inspector at the device location. The
  appointment receives the tour date as its planned date, tour planning
  creates the tour and optimises the order; the tour then opens.
- Devices marked **no coordinates** stay in the tour but are not included in
  the route calculation.
- Inspector tours require the planning module. Without this module the page
  shows a note and no button for planning.

**Permission:** **Maintain inspection profiles and duties**.

## Audit report

Menu **Test equipment** → **Audit report** (page title **Inspection
management audit report**). You choose the period in the page's filter bar;
the default is the last three months.

- Current state, independent of the period: **Inspection obligations**
  (active obligations), **Overdue** (due date plus tolerance exceeded), **Due
  soon** (within the profile's warning lead time) and **Blocked (inspection
  management)** (active blocks due to overdue or failed inspections).
- Related to the period: **Inspections in the period**, **Failed**,
  **Inspection rate** (share of passed inspections, including those with
  restrictions), **Certificates**, **Inspection costs in period** and the
  costs of up to three inspection types.
- Tables: **Inspection obligations by inspection type**, **Inspections by
  inspector (top 10)** and **Deviations (failed)** with asset, time and
  remark.

**Freeze snapshot** stores the figures of the selected period immutably. The
last ten snapshots are listed under **Frozen snapshots (P2)** with period,
creation time, number of overdue obligations and inspection rate.

**Permission:** **List inspection duties and test equipment** – this also
applies to freezing a snapshot.
