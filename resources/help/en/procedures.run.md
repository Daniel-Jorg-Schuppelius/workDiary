---
title: "Run a procedure"
topic: procedures.run
version: 1
audience: []
related:
    - protocols.create
---

A procedure (work instruction with mandatory steps) is started on a
**work order** or **asset**. While it is running:

- **Mandatory steps** must be completed in the defined order.
- **Backup steps** require proof (hash + size or an external storage
  link) before you can continue.
- **Four-eyes steps** need confirmation by a **second person** with a
  matching role. The confirmation appears asynchronously in their task
  list.
- **Deviations** are recorded with a reason and a follow-up action –
  skipping without a reason is not possible.

When the procedure is completed, the template version used is pinned.
Later changes to the template do **not** apply retroactively.
