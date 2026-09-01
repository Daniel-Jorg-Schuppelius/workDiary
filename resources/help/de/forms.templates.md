---
title: "Formularvorlagen pflegen"
topic: forms.templates
version: 1
audience:
    - admin
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

Formularvorlagen definieren Checklisten und Erfassungen ohne Code –
per Felddefinition.

Typischer Ablauf:

1. **Vorlage anlegen**: Name, Beschreibung und Felder. Je Feld:
   Schlüssel, Bezeichnung, Typ („Text", „Mehrzeiliger Text", „Zahl",
   „Datum", „Auswahl", „Checkbox"), Pflicht ja/nein, bei Auswahl die
   Optionen, optional Hilfetext und Einheit.
2. **Aktivieren**: erst im Status „Aktiv" ist die Vorlage ausfüllbar.
3. **Archivieren**: nimmt die Vorlage aus der Ausfüll-Auswahl –
   bestehende Ausfüllungen bleiben lesbar.

Wichtige Status: „Entwurf" → „Aktiv" → „Archiviert".

Snapshot-Prinzip: Jede Ausfüllung friert die Felddefinition zum
Ausfüllzeitpunkt ein. Feldänderungen wirken daher **nur auf neue
Ausfüllungen** – alte bleiben unverändert und auswertbar. Auch das
Löschen einer Vorlage macht bestehende Ausfüllungen nicht unlesbar.

Berechtigungen: Formularvorlagen werden von Teamleitungen oder anderen
ausdrücklich berechtigten Personen angelegt, bearbeitet, aktiviert,
archiviert und gelöscht.

Tipp: Stabile Feld-Schlüssel beibehalten, wenn du Auswertungen über
mehrere Vorlagen-Generationen hinweg vergleichen willst.
