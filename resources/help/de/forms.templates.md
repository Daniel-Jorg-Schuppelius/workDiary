---
title: "Formularvorlagen pflegen"
topic: forms.templates
version: 4
keywords:
    - Formular erstellen
    - Formulardesigner
    - Formulareditor
    - Formularbaukasten
    - Checkliste erstellen
    - Formularfelder
    - Feldtypen
    - Auswahlfeld
    - Pflichtfeld
    - Formular aktivieren
    - Formular archivieren
    - Abschnitte
    - Ergänzung erlauben
audience:
    - admin
    - geschaeftsfuehrung
    - teamleitung
modules:
    - module.forms
related:
    - forms.fill
    - glossary.core
---

Formularvorlagen definieren Checklisten und Erfassungen ohne Code –
per Felddefinition. Sie finden sie unter **System** → **Regeln &
Prozesse** → **Formularvorlagen** oder über die Schaltfläche
**Formularvorlagen** in der Übersicht **Formulare**.

Typischer Ablauf:

1. **Vorlage anlegen**: **Name**, **Beschreibung**, optional **Gültig ab**
   und **Gültig bis** sowie **Zuordnung: Auftragstyp** und **Zuordnung:
   Kunde** (bei „alle“ gilt die Vorlage überall). Darunter folgen die
   **Felder**; mit **Feld hinzufügen** kommt ein weiteres dazu. Je Feld:
   **Feldbezeichnung**, **Feldtyp** und **Pflicht**, je nach Typ die
   **Optionen** (kommagetrennt), **Einheit** oder **Wertebereich** (Min,
   Max), optional ein **Hilfetext** und eine Bedingung **Sichtbar wenn**,
   mit der ein Feld nur erscheint, wenn ein anderes Feld einen bestimmten
   Wert hat.
2. **Aktivieren**: Neue Vorlagen beginnen im Status „Entwurf“; erst im
   Status „Aktiv“ ist die Vorlage ausfüllbar.
3. **Archivieren**: nimmt die Vorlage aus der Ausfüll-Auswahl –
   ausgefüllte Formulare bleiben lesbar. Eine archivierte Vorlage lässt
   sich wieder aktivieren.

Feldtypen: „Text“, „Mehrzeiliger Text“, „Zahl“, „Checkbox“, „Auswahl“,
„Mehrfachauswahl“, „Datum“, „Datum und Uhrzeit“, „Skala“, „Foto“,
„Datei“, „Unterschrift“, „Abschnitt“ und „Messwert“. Einen eigenen
Feld-Schlüssel geben Sie nicht ein; jede Feldbezeichnung darf in einer
Vorlage nur einmal vorkommen.

Wichtige Status: „Entwurf“ → „Aktiv“ → „Archiviert“.

Snapshot-Prinzip: Jedes ausgefüllte Formular friert die Felddefinition
zum Ausfüllzeitpunkt ein. Feldänderungen wirken daher **nur auf neu
ausgefüllte Formulare** – alte bleiben unverändert und auswertbar. Auch
das **Löschen** einer Vorlage macht ausgefüllte Formulare nicht unlesbar.

Berechtigungen: Formularvorlagen anlegen, bearbeiten, aktivieren,
archivieren und löschen darf, wer das Recht **Formularvorlagen pflegen**
hat (standardmäßig die Teamleitung).

Lange Formulare: Gliedern Sie lange Vorlagen mit Feldern vom Typ
„Abschnitt“ – beim Ausfüllen wird daraus je Abschnitt eine Karte mit
Fortschritt, ab vier Abschnitten mit Inhaltsleiste; bis sechs Fragen
bleibt das Formular eine Karte. Bei „Auswahl“ und „Mehrfachauswahl“ legt
**Ergänzung erlauben** ein zusätzliches Feld für eine Anmerkung zur
Antwort an. Auswahlen mit bis zu sechs Optionen erscheinen als Knöpfe,
längere als Auswahlliste. Ändern Sie die Antwortart einer Frage, während
jemand einen Entwurf ausfüllt, bleibt die frühere Antwort dort als
Ergänzung erhalten.

Tipp: Das System leitet die interne Zuordnung eines Felds aus seiner
Feldbezeichnung ab. Behalten Sie Feldbezeichnungen daher bei, wenn Sie
ausgefüllte Formulare über mehrere Vorlagenstände hinweg vergleichen
wollen.
