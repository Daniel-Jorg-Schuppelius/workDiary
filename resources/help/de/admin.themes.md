---
title: "Themes"
topic: admin.themes
version: 1
audience:
    - admin
modules:
    - module.theming
related:
    - admin.handbook
    - admin.license
---

Themes sind Design-Presets deiner Organisation für die Oberfläche.
Sie definieren die Farb- und Geometriepalette (helles oder dunkles
Schema). Neben den mitgelieferten Themes kannst du eigene Themes
anlegen.

Je Theme legst du fest:

- **Schlüssel und Bezeichnung**: eindeutiger Schlüssel (nach dem
  Anlegen unveränderlich) und Anzeigename.
- **Schema**: hell oder dunkel.
- **Farben**: Basis-, Akzent- und Statusfarben (z. B. Hintergrund,
  Primär, Sekundär, Akzent, Neutral sowie Info/Erfolg/Warnung/
  Fehler). Fehlende Kontrastfarben werden automatisch abgeleitet.
- **Geometrie**: Eckenradien und Rahmenbreite.

Ein Mindestkontrast (Neutral zu Neutral-Text) wird erzwungen, damit
Seitenleiste und Panels lesbar bleiben.

Standard festlegen:

- Du kannst je Modus einen Standard setzen (Standard hell / Standard
  dunkel). Er gilt für alle Mitglieder ohne eigene Theme-Auswahl.

Lizenz/Module: Eigene Themes gehören zum Modul **Theming** und sind
in höheren Plänen verfügbar. Bei einem Downgrade bleibt ein aktives
Theme bestehen (rein kosmetisch); nur der Editor zum Anlegen/
Bearbeiten wird gesperrt. Details im Kapitel **Lizenz**.

Berechtigung: Themes verwalten dürfen Organisations-Administratoren.

Risiken: Das Löschen eines genutzten Themes setzt betroffene Nutzer
auf ein Fallback-Theme zurück. Prüfe Farbänderungen auf Lesbarkeit,
bevor du ein Theme als Standard setzt.
