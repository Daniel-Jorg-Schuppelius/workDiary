---
title: "Feature scope"
topic: scope.overview
version: 1
keywords:
    - enable modules
    - disable modules
    - hide features
    - declutter menu
    - module selection
    - preset
    - set start page
    - landing page per role
    - home page
    - turn off features
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

## Scope mode and compact start

Since feature 165 the page also shows the **scope mode**: "Deliberately
limited" keeps modules that are added to the license later deactivated until
you set them up under *Extend feature scope*; "Full licensed scope" activates
license growth immediately. The **Compact start** card leads to the setup
with a few goals (customers and orders, time, team planning, quotes and
invoices) and, after applying, to the revert that restores the state before.
Manual changes since applying are listed and only overwritten on request.
