---
title: "Zuschlagsregeln"
topic: admin.surcharge-rules
version: 1
audience:
    - admin
    - buchhaltung
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
- Änderungen wirken auf **künftige Exporte**; bereits erzeugte
  Exporte bleiben unverändert (Korrektur über Re-Export).

Berechtigungen: Zuschlagsregeln dürfen nur von ausdrücklich
berechtigten Personen angelegt, bearbeitet und gelöscht werden.
