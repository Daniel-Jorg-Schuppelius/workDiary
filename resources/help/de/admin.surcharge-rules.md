---
title: "Zuschlagsregeln"
topic: admin.surcharge-rules
version: 1
audience:
    - admin
    - buchhaltung
modules:
    - module.lohn
related:
    - exports.payroll
    - finance.transfers
    - admin.handbook
    - glossary.core
---

Zuschlagsregeln definieren Nacht-, Wochenend-, Feiertags- und
benutzerdefinierte Zeitfenster-Zuschläge. Beim Zeitexport werden
Anwesenheiten danach zerlegt und je Lohnart als eigene Zeilen
ausgewiesen.

Typischer Ablauf:

1. **Regel anlegen**: eindeutiger Code (z. B. „night"), Bezeichnung
   (z. B. „Nachtzuschlag"), Art.
2. **Art** wählen: „Nacht" (Zeitfenster, auch über Mitternacht, z. B.
   22:00–06:00), „Samstag", „Sonntag", „Feiertag" (gesetzliche
   Feiertage automatisch) oder „Benutzerdefiniert" (freies
   Zeitfenster).
3. **Prozentsatz** (0–999,99 %) und optional die **Lohnart-Nummer**
   für DATEV/Lexware (z. B. „2010") hinterlegen.
4. Optional **Gültigkeit** (von/bis), **Priorität** und **aktiv**
   setzen.

Wichtige Regeln:

- Bei überlappenden Regeln gewinnt der **höchste Prozentsatz** – es
  wird nicht addiert. Bei Gleichstand entscheidet die Priorität.
- Zeitfenster gelten nur für die Arten „Nacht" und
  „Benutzerdefiniert".
- Bedingungen (Teams, Standorte, Schichttypen) schränken eine Regel
  ein: leer = gilt für alle; mehrere Bedingungen sind UND-verknüpft.
  Der Standort wird über Terminal-Stempel erkannt — ohne ermittelbaren
  Kontext greift eine bedingte Regel nicht. Standorte können eine
  eigene Feiertags-Region tragen (Feiertagszuschlag am Einsatzort).
- Änderungen wirken auf **künftige Exporte**; bereits erzeugte
  Exporte bleiben unverändert (Korrektur über Re-Export). Historische
  Zeiträume bewertet nur die auditierte Neuberechnung
  (`rules:recalculate`) neu — nie eine stille Regeländerung.

Berechtigungen: Zuschlagsregeln dürfen nur von ausdrücklich
berechtigten Personen angelegt, bearbeitet und gelöscht werden.
