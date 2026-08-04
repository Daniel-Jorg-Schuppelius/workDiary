---
title: "Dictionary"
topic: admin.text-corrections
version: 1
audience:
    - admin
---

The **dictionary** fixes recurring spelling mistakes automatically —
deterministic and without AI. Each entry is a pair "wrong → right".

- **Effect**: when generated position texts are built (billing transfers,
  invoice drafts, invoice preview). The recorded time entries themselves
  remain unchanged.
- **Matching**: whole words or phrases only, case-insensitive; the
  correction's spelling is preserved (UPPER stays UPPER, sentence starts
  are capitalized).
- **Learning**: when a position text is corrected manually, the app detects
  1:1 word replacements and offers to "remember" them — entries are only
  added after confirmation, never silently. Such entries show as "Learned".
- **Deactivate instead of delete**: a deactivated entry has no effect but
  remains traceable.

Maintenance requires the finance configuration permission because entries
change invoice output.
