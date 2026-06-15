---
title: "Prozedur-Designer"
topic: procedures.designer
version: 1
audience: []
related:
    - procedures.run
---

Im **Prozedur-Designer** legst du verbindliche Abläufe (Arbeitsanweisungen,
Checklisten) an, die später auf Aufträgen ausgeführt werden.

## Vorlage und Versionen

- Eine **Vorlage** hat einen eindeutigen **Code**, einen Namen und einen
  optionalen Bereich (z. B. `it`, `hvac`).
- Schritte gehören immer zu einer **Version**. Solange eine Version ein
  **Entwurf** ist, kannst du Schritte frei bearbeiten.
- Mit **Veröffentlichen** wird die Version gültig gesetzt und **unveränderlich**.
  Korrekturen erzeugen eine **neue Version** – laufende/alte Aufträge behalten
  ihre damalige Version.

## Schritte

Jeder Schritt hat einen **Typ** (Bestätigung, Messwert, Foto, Datei,
Backup-Nachweis, Unterschrift, Freigabe …). Zusätzlich steuerbar:

- **Pflicht**: muss vor Abschluss des Laufs einen finalen Status haben.
- **Sperrend**: blockiert nachfolgende Schritte, bis dieser erledigt ist.
- **Vier-Augen**: verlangt die Gegenzeichnung einer zweiten Person.
- **Nachweis** (Backup/Foto/Datei/Messwert/Unterschrift) und optionale
  **Rolle/Qualifikation**.
- **Bedingung (wenn-dann)**: Der Schritt wird nur relevant, wenn ein anderer
  Schritt einen bestimmten Wert/Status hat.

## Automatische Zuordnung

Über **Auftragstypen** und **Tags** legst du fest, für welche Aufträge die
Vorlage automatisch vorgeschlagen wird. Auf der Auftragsdetailseite erscheinen
passende, veröffentlichte Vorlagen als Start-Button.
