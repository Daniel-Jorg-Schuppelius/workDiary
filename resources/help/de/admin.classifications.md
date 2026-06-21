---
title: "Klassifikationen & Pflichtregeln"
topic: admin.classifications
version: 1
audience:
    - admin
related:
    - catalog.entry-types
    - diary-entries.create
    - admin.import
    - glossary.core
---

Klassifikationen sind organisationsweite Wertelisten je Domäne, etwa
Auftragstypen, Tätigkeiten, Fehlertypen, Ursachen, Ergebnisse, Prioritäten,
Kulanz- und Nacharbeitsgründe, Produktgruppen und Dienstmitteltypen. Jede
Klassifikation hat einen Code, eine Bezeichnung sowie optional Farbe, Symbol
und Sortierung.

Plattform-Vorgaben stehen allen Organisationen zur Verfügung; du kannst sie
für deine Organisation überschreiben, eigene Werte ergänzen, die Reihenfolge
je Domäne anpassen oder eine Plattform-Vorgabe für die Organisation
deaktivieren. Über den CSV-Import lassen sich viele Werte auf einmal anlegen
oder aktualisieren; Pflichtspalten sind Domäne, Code und Bezeichnung.

Pflichtregeln verknüpfen einen Auftragstyp mit einer Pflicht-Domäne und
legen fest, ab welcher Phase die Angabe erforderlich ist – bei Erstellung,
vor Abschluss oder vor Signatur – sowie ob die Regel blockierend ist oder
nur ein Hinweis. Minimal- und Maximalanzahl, Mehrfachauswahl und eine
JSON-Bedingung steuern, wann und wie viele Werte verlangt werden.
