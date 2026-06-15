# Import, Migration und Onboarding

## Status

In Progress — MVP-Bausteine liegen vor:
[Onboarding-Checkliste](../onboarding-checkliste.md) (MVP-048, Issue #47) und
[CSV-Import](../csv-import.md) (MVP-049, Issue #48). Der CSV-Import-Wizard deckt
inzwischen **Kunden, Projekte, Nutzer, Materialien, Schichtpläne und Fahrzeuge**
ab (eine gemeinsame Spec-Registry, Preflight, Fehlerprotokoll, Idempotenz).
Offen bleibt die **Legacy-Migration vorhandener WorkDiary-/Tagebuchdaten**
(umfangreiches, eigenständiges Vorhaben) sowie branchenspezifische
Beispielkonfigurationen.

## Ziel

Neue Kunden sollen WorkDiary schnell einführen können. Dafür braucht es
Stammdatenimporte, Migrationspfade, Onboarding-Assistenten und Prüfberichte für
Kunden, Projekte, Mitarbeitende, Materialien, Fahrzeuge, Schichtpläne,
Zeiterfassungsdaten und Legacy-Daten.

## Warum

Ein gutes Produkt scheitert im Verkauf, wenn der Wechsel zu aufwendig ist.
Kunden müssen ihre bestehenden Daten übernehmen und die ersten Workflows schnell
produktiv nutzen können.

## MVP

- Import-Assistent für CSV/Excel-Stammdaten.
- Vorlagen für Kunden, Projekte, Mitarbeitende, Materialien, Fahrzeuge.
- Validierung mit Fehlerliste und Vorschau.
- Legacy-Migration für vorhandene WorkDiary-/Tagebuchdaten.
- Onboarding-Checkliste pro Mandant.
- Beispielkonfigurationen je Branche.

## Akzeptanzkriterien

- Ein Kunde kann Stammdaten ohne Entwickler importieren.
- Fehlerhafte Zeilen werden erklärt und verhindern keine sauberen Teilimporte.
- Imports sind protokolliert und wiederholbar.
- Onboarding zeigt fehlende Grundkonfigurationen.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- [DATEV- und Finanzschnittstelle](./045-datev-finanzschnittstelle.md)
- Kunden, Projekte, Nutzer
- Inventar, Dienstmittel und Assets
- Dienstplan
- Legacy-Import

## GitHub Issues

- TBD
