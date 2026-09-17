---
title: "Feature scope"
topic: scope.overview
version: 1
audience:
    - admin
related:
    - admin.handbook
    - navigation.customize
---

The **Feature scope** page defines which modules your organization uses
visibly. It is a shortcut for the module configuration: only the module
status is switched — **no data is ever deleted**, and everything returns
when you reactivate.

## Presets

A preset (e.g. “Lean start” or “Service & trades”) switches the module list
in one step. Afterwards you can toggle individual modules.

## Branch-profile recommendation

If your organization has a branch profile installed, the page shows its
module recommendation. It is never applied automatically — you confirm it
deliberately.

## Start page per role

Below the modules you decide where a role lands after signing in – for example
the time clock for field staff or the receipt flow for accounting. A person's
own choice in the profile always takes precedence. If a person has several
roles, the first one in the displayed order that has a page set applies. A page
the person may not open is skipped; without a setting the default stays.

## Limits

- Unlicensed modules cannot be enabled here; that requires license
  management.
- Hiding does not change permissions. Locked pages respond with a notice
  (HTTP 423) instead of losing data.
