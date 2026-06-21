---
title: "Themes"
topic: admin.themes
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.license
---

Themes are your organization's design presets for the interface.
They define the color and geometry palette (light or dark scheme).
Besides the built-in themes you can create your own.

For each theme you define:

- **Key and label**: a unique key (immutable after creation) and a
  display name.
- **Scheme**: light or dark.
- **Colors**: base, accent and status colors (e.g. background,
  primary, secondary, accent, neutral, plus info/success/warning/
  error). Missing contrast colors are derived automatically.
- **Geometry**: corner radii and border width.

A minimum contrast (neutral to neutral text) is enforced so that the
sidebar and panels stay readable.

Set default:

- You can set a default per mode (default light / default dark). It
  applies to all members who have not chosen their own theme.

License/modules: custom themes belong to the **theming** module and
are available on higher plans. On a downgrade an active theme
remains in place (purely cosmetic); only the editor for creating/
editing is locked. See the **License** chapter for details.

Permissions: themes may be managed by organization administrators.

Risks: deleting a theme that is in use resets affected users to a
fallback theme. Check color changes for readability before setting a
theme as the default.
