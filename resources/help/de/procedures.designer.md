---
title: "Prozedur-Designer"
topic: procedures.designer
version: 3
keywords:
    - Arbeitsanweisung
    - Checkliste erstellen
    - SOP
    - Standardarbeitsanweisung
    - Ablaufvorlage
    - Prozessvorlage
    - Workflow
    - Pflichtschritte
    - Vier-Augen-Prinzip
    - Bedingung wenn dann
    - Version veröffentlichen
audience: []
related:
    - procedures.run
---

Im **Prozedur-Designer** legen Sie verbindliche Abläufe (Arbeitsanweisungen,
Checklisten) an, die später auf Aufträgen ausgeführt werden. Die Vorlagen
finden Sie unter **System** → **Regeln & Prozesse** → **Prozedurvorlagen**;
**Bearbeiten** öffnet den Designer einer Vorlage.

## Vorlage und Versionen

- Eine **Vorlage** (**Vorlage anlegen**) hat einen eindeutigen **Code**,
  einen **Namen**, einen optionalen **Bereich** (z. B. `it`, `hvac`) und
  eine **Beschreibung**; im Designer kommt die **Risikostufe** hinzu.
- Schritte gehören immer zu einer **Version**. Solange eine Version ein
  **Entwurf** ist, können Sie Schritte frei bearbeiten und mit
  **Speichern** sichern; eine **Änderungsnotiz** hält fest, was sich
  geändert hat.
- Mit **Veröffentlichen** wird die Version gültig gesetzt und
  **unveränderlich**. Korrekturen erfordern eine **Neue Version** –
  laufende und alte Aufträge behalten ihre damalige Version.

## Schritte

Mit **Schritt hinzufügen** oder **Aus Bibliothek einfügen** (aus der
**Schrittbibliothek**) ergänzen Sie Schritte. Jeder Schritt hat einen
**Code**, eine **Bezeichnung**, eine optionale **Beschreibung** und einen
**Typ**, etwa „Bestätigung“, „Text“, „Zahl/Messwert“, „Auswahl“, „Foto“,
„Datei“, „Backup-Nachweis“, „Unterschrift“, „Materialerfassung“,
„Messreihe“, „Freigabe (Vier-Augen)“ oder „Wartezeit“. Zusätzlich
steuerbar:

- **Pflicht**: muss vor Abschluss des Laufs einen finalen Status haben.
- **Sperrend**: blockiert nachfolgende Schritte, bis dieser erledigt ist.
- **Vier-Augen**: verlangt die Gegenzeichnung einer zweiten Person.
- **Nachweis** („Backup“, „Datei“, „Foto“, „Messwert“, „Unterschrift“
  oder „Keiner“) sowie optional **Erforderliche Rolle** und
  **Qualifikation**.
- **Bedingung: Schritt** und **Bedingung: Wert** (wenn-dann): Der Schritt
  wird nur relevant, wenn ein anderer Schritt einen bestimmten Wert hat.

## Automatische Zuordnung

Über **Auftragstypen** und **Tags** legen Sie fest, für welche Aufträge die
Vorlage automatisch vorgeschlagen wird. Auf der Auftragsdetailseite
erscheinen passende, veröffentlichte Vorlagen in der Karte **Prozeduren**
unter „Für diesen Auftrag vorgeschlagene Prozeduren:“ als Start-Button.
