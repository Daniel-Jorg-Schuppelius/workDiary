---
title: "Themes"
topic: admin.themes
version: 5
keywords:
    - dark mode
    - light mode
    - color scheme
    - custom colors
    - appearance
    - branding
    - corporate colors
    - design preset
    - skin
    - contrast
    - look and feel
audience:
    - admin
modules:
    - module.theming
related:
    - admin.handbook
    - admin.license
    - navigation.interface
---

Themes are your organization's design presets for the interface. They
define the color and geometry palette (light or dark base mode). In
addition to the **Predefined themes**, you can create your own themes
under **Custom themes** (twelve at most).

With **New theme** or **Edit** you define for each theme:

- **Master data**: **Key** (lowercase letters, digits, hyphen;
  immutable after creation), **Name** and **Base mode** (**Light** or
  **Dark**).
- **Colors**: base, accent and status colors (e.g. background,
  primary, secondary, accent, neutral as well as
  info/success/warning/error). Text colors are derived automatically
  from the contrast.
- **Geometry**: corner radii and **Border width**.

The **Preview** in the dialog shows the effect immediately. A minimum
contrast (neutral vs. neutral text) is enforced so that the sidebar
and panels stay readable.

Setting the default:

- In the **Organization default theme** area you choose one theme each
  for **Light mode** and **Dark mode** and save with **Apply**. The
  choice applies to all members without their own theme selection in
  their profile; custom themes then show the badge **Default light** or
  **Default dark**.
- The entry **Default (Corporate)** or **Default (Corporate Dark)**
  removes your selection again; the bundled themes Corporate (light)
  and Corporate Dark (dark, same colors on a dark background) then
  apply.

License/modules: custom themes belong to the **Custom themes** module
and are available in higher plans. After a downgrade an active theme
remains in place (purely cosmetic); the **Themes** page with editor
and default selection is then locked. Details in the **License**
chapter.

Permission: organization administrators may manage themes.

Risks: deleting a theme in use resets affected users to a fallback
theme; if it was set as default, the bundled default applies again.
Check color changes for readability before setting a theme as the
default.
