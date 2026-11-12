---
title: "Run a procedure"
topic: procedures.run
version: 4
keywords:
    - complete checklist
    - follow work instruction
    - start procedure
    - mandatory steps
    - record deviation
    - countersign
    - four-eyes check
    - backup proof
    - confirm step
audience: []
related:
    - protocols.create
---

A procedure (work instruction with mandatory steps) is started on a
**work order** or **asset**. While it is running:

- **Mandatory steps** must be completed in the defined order.
- **Backup steps** require proof (hash + size or an external storage
  link) before you can continue.
- **Four-eyes steps** need the countersignature of a **second person**
  with a matching role (see below).
- **Deviations** are recorded with a reason and a follow-up action –
  skipping without a reason is not possible.

When the procedure is completed, the template version used is pinned.
Later changes to the template do **not** apply retroactively.

## Four-eyes steps

The **Four-eyes approval** bar on the step shows the state: not requested
yet, requested, taken over or countersigned.

1. The person executing the step clicks **Request second person**. Team
   leads receive a notification with a link to the run.
2. Another authorised person opens the run and clicks **Countersign as
   second person**. Whoever requested cannot countersign themselves.
3. Only then can the step be completed.

A countersignature can be **revoked** with a reason (permission “Revoke
four-eyes approval”, usually team leads or administrators). The step then
falls back to “open”, the request remains and another person can
countersign. Request, countersignature and revocation are recorded with
person and time in the run history.

## Backup proof

A step of type “Backup record” shows the **Backup proof** card.

1. **Record backup proof** opens the dialog: backed-up system, scope, time
   of backup, storage (**Attachment** with a file or **External storage**
   with location and size) and verification method. Whatever the template
   fixes is not offered; minimum size and maximum age are checked when
   saving.
2. With the method **Checksum comparison** you enter the expected
   checksum. For an uploaded file the system recalculates the checksum and
   confirms the proof itself; with external storage the verifying person
   enters the recalculated checksum.
3. **Restore test** and **Management confirmation** are confirmed by a
   **second person** with the permission “Verify backup record” via
   **Confirm proof** – not by the person who recorded it.
4. **Reject proof** requires a reason; afterwards you record a new proof
   (**Replace proof**).

Only with a confirmed proof can the step be completed. A later step that
requires a prior backup becomes available with it. If no backup is
possible, record this as a **deviation**. The location of an external
backup is visible only with the permission “View external backup storage”.

## Qualification

If a step names a **Qualification**, only someone who currently holds this
qualification (abbreviation or name as listed under the employees'
qualifications) can complete it. The same applies to the second person if
the template requires a qualification for the countersignature.

## Critical deviation and risk acceptance

A deviation is shown on the step with type, severity, reason and follow-up
action. A **critical** deviation blocks completion of the run as long as
neither an open issue nor a follow-up order has been created from it.
Whoever has the permission “Accept critical deviation” opens **Accept
risk** on the deviation and enters a reason. Person, time and reason are
recorded in the run history; the report “Procedure deviations” shows the
decision.

## Critical procedures

A run of a template with risk level “Critical” can only be completed when
at least one four-eyes step is done and countersigned.

## Starting on an asset

Templates that the designer assigns by asset category, product or SLA
contract appear on the asset record in the **Procedures** card under
“Procedures intended for this asset:”. If the asset is blocked, only a
template with the option **May run on blocked assets** starts.

**Run again:** If you choose this follow-up action for a deviation, WorkDiary immediately starts a repeat run of the same template on the same order or asset. The deviation links to it, and a critical deviation then no longer blocks completion. If the asset is blocked, no run is created; the reason is in the history.
