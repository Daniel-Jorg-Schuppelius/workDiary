---
title: "Themes"
topic: admin.themes
version: 5
keywords:
    - Darkmode
    - Dunkelmodus
    - dunkles Design
    - heller Modus
    - Farbschema
    - Farben anpassen
    - Erscheinungsbild
    - Corporate Design
    - Firmenfarben
    - Designvorlage
    - Kontrast
    - Eckenradius
audience:
    - admin
modules:
    - module.theming
related:
    - admin.handbook
    - admin.license
    - navigation.interface
---

Themes sind Design-Presets Ihrer Organisation für die Oberfläche.
Sie definieren die Farb- und Geometriepalette (heller oder dunkler
Grundmodus). Neben den mitgelieferten Themes (Bereich **Vordefinierte
Themes**) können Sie unter **Eigene Themes** eigene Themes anlegen
(höchstens zwölf).

Über **Neues Theme** bzw. **Bearbeiten** legen Sie je Theme fest:

- **Stammdaten**: **Schlüssel** (Kleinbuchstaben, Ziffern, Bindestrich;
  nach dem Anlegen unveränderlich), **Name** und **Grundmodus**
  (**Hell** oder **Dunkel**).
- **Farben**: Basis-, Akzent- und Statusfarben (z. B. Hintergrund,
  Primär, Sekundär, Akzent, Neutral sowie Info/Erfolg/Warnung/Fehler).
  Textfarben werden automatisch aus dem Kontrast abgeleitet.
- **Geometrie**: Eckenradien und **Rahmenbreite**.

Die **Vorschau** im Dialog zeigt die Wirkung sofort. Ein
Mindestkontrast (Neutral zu Neutral-Text) wird erzwungen, damit
Seitenleiste und Panels lesbar bleiben.

Standard festlegen:

- Im Bereich **Standard-Theme der Organisation** wählen Sie je ein
  Theme für **Hell-Modus** und **Dunkel-Modus** und speichern mit
  **Übernehmen**. Die Wahl gilt für alle Mitglieder ohne eigene
  Theme-Wahl im Profil; eigene Themes zeigen dann das Kennzeichen
  **Standard hell** bzw. **Standard dunkel**.
- Der Eintrag **Standard (Corporate)** bzw. **Standard (Corporate
  Dark)** hebt Ihre Auswahl wieder auf; dann gelten die mitgelieferten
  Themes Corporate (hell) und Corporate Dark (dunkel, gleiche Farben
  auf dunklem Grund).

Lizenz/Module: Eigene Themes gehören zum Modul **Eigene Themes** und
sind in höheren Plänen verfügbar. Bei einem Downgrade bleibt ein
aktives Theme bestehen (rein kosmetisch); die Seite **Themes** mit
Editor und Standardauswahl ist dann gesperrt. Details im Kapitel
**Lizenz**.

Berechtigung: Themes verwalten dürfen Organisations-Administratoren.

Risiken: Das Löschen eines genutzten Themes setzt betroffene Nutzer
auf ein Fallback-Theme zurück; war es als Standard gesetzt, gilt wieder
der mitgelieferte Standard. Prüfen Sie Farbänderungen auf Lesbarkeit,
bevor Sie ein Theme als Standard setzen.
