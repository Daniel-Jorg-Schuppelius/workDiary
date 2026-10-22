---
title: "Run a procedure"
topic: procedures.run
version: 2
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
