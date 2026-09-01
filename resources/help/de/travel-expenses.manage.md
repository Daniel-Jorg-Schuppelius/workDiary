---
title: "Fahrten, Spesen & Pauschalen"
topic: travel-expenses.manage
version: 1
audience: []
modules:
    - module.spesen
related:
    - invoices.manage
    - exports.payroll
    - reports.overview
---

Fahrtenbuch, Spesen und Verpflegungspauschalen dokumentieren
dienstliche Reisen getrennt, aber mit gemeinsamem Zeitraum- und
Belegbezug.

Typischer Ablauf:

1. Fahrt mit Datum, Strecke, Zweck, Fahrzeug und Kilometerständen
   erfassen.
2. Ausgaben mit Kategorie, Betrag, Zahlungsart und Beleg ergänzen.
3. Bei mehrtägigen Reisen die Pauschale aus Reisezeiten und Reiseziel
   berechnen lassen.
4. Angaben prüfen und zur Genehmigung oder Abrechnung weitergeben.

Belege, Kilometerstände und Reisezeiten müssen plausibel sein.
Genehmigte oder abgerechnete Datensätze werden nicht still geändert;
Korrekturen brauchen einen nachvollziehbaren Weg.

## Auslage als Beleg in die Buchhaltung

Eine **genehmigte** Auslage lässt sich im Beleg-Dialog direkt als
Einkaufsbeleg an das führende Buchhaltungssystem übergeben — statt sie dort
ein zweites Mal zu erfassen. Die externe Beleg-ID kommt beim Anlegen zurück;
die Dublette kann gar nicht erst entstehen.

Drei Regeln:

- **Nur genehmigte Auslagen.** Der Push ist unwiderruflich — das Zielsystem
  kennt für Belege weder Ändern noch Löschen. Korrekturen laufen dort als
  Gegenbeleg.
- **Ohne Buchungskategorie kein Push.** Die Zuordnung wird je
  Auslagenkategorie gepflegt (Verwaltung → Auslagenkategorien); eine geratene
  Kategorie wäre schlimmer als die Fehlermeldung.
- **Ab der Übergabe führt der Beleg.** Die Verknüpfung lässt sich nicht mehr
  lösen — der Beleg existiert, ob verknüpft oder nicht.

Die Belegdateien der Auslage werden mit übergeben — ohne Datei ist der Beleg
für die Buchhaltung wertlos.
