# Auswertungen und Entscheidungsgrundlagen

## Status

Done — MVP umgesetzt; konzipiert in MVP-039 bis MVP-043:
[Kundenanalyse](../kundenanalyse.md),
[Auftragstypanalyse](../auftragstypanalyse.md),
[Produkt-/Objektanalyse](../produkt-analyse.md),
[Drilldown](../report-drilldown.md),
[CSV/PDF-Export](../report-export.md).

## Ziel

WorkDiary soll nicht nur Daten sammeln, sondern Firmen belastbare Auswertungen
liefern. Grafische und zahlenbasierte Auswertungen sollen zeigen, wie Arbeit
wirklich läuft: welche Kunden schwierig sind, welche Produkte wiederkehrende
Probleme verursachen, ob Fortbildungen nötig sind, wie effizient Teams arbeiten
und welche Aufträge wirtschaftlich oder organisatorisch auffällig sind.

## Warum

Aufzeichnung hat nur dann strategischen Wert, wenn daraus Entscheidungen
abgeleitet werden können. Unternehmen müssen erkennen können, ob Aufwand
planbar ist, ob Preise passen, ob bestimmte Kunden dauerhaft zu viel
Nacharbeit erzeugen, ob Mitarbeitende Unterstützung brauchen oder ob ein
Produkt, Dienstmittel oder Prozess systematisch Probleme macht.

## Leitfragen

- Welche Kunden verursachen überdurchschnittlich viele Einsätze, Rückfragen,
  Nacharbeiten, Eskalationen oder nicht abrechenbare Zeiten?
- Welche Produkte, Anlagen, Geräte oder Dienstmittel tauchen wiederholt in
  Problemaufträgen auf?
- Welche Auftragstypen dauern länger als geplant oder sind häufig defizitär?
- Wo entstehen Wartezeiten, Reisezeiten, Wiederholfahrten oder unnötige
  Unterbrechungen?
- Welche Teams oder Mitarbeitenden benötigen Fortbildung, Einweisung oder
  bessere Dokumentation?
- Welche Tätigkeiten sind profitabel, kostendeckend oder dauerhaft kritisch?
- Welche Kunden- oder Produktprobleme sollten in Vertrieb, Servicevertrag,
  Einkauf oder Qualitätsmanagement zurückgespielt werden?

## Vorhandene Basis

- Reports für Arbeitsbilanz, Anwesenheit, Woche pro Nutzer, Projekte,
  Kunden/Projekte, Fuhrpark, Material, Abrechnung, Operations, Qualifikationen,
  Spesen, Krankheit und Audit-Aktivität.
- Strukturierte Daten aus `Customer`, `Project`, `Task`, `DiaryEntry`,
  `TimeEntry`, `Timesheet`, `MaterialUsage`, `Vehicle`, `TravelLog`,
  `Expense`, `Qualification` und `AuditLog`.
- Dashboard-Kennzahlen und Filter nach Zeitraum.

## MVP

- Management-Dashboard mit Zeitraumfilter und Rollenberechtigung.
- Kundenanalyse: Aufwand, Umsatz-/Abrechnungsbezug, Nacharbeit, offene Punkte,
  nicht abrechenbare Zeiten und Trend.
- Produkt-/Problemcluster: wiederkehrende Tags, Materialien, Auftragstypen,
  Fehlerursachen und betroffene Kunden.
- Effizienzanalyse: Plan/Ist, Bearbeitungszeit, Reisezeitanteil, Wartezeit,
  Wiederholfahrten und Abschlussquote.
- Schulungsindikatoren: häufige Fehlerbilder, Qualifikationslücken,
  lange Bearbeitungszeiten je Tätigkeit, wiederkehrende Rückfragen.
- Grafische Auswertungen: Zeitreihen, Balkendiagramme, Heatmaps,
  Top-N-Listen, Verteilungen und Ampel-Kennzahlen.
- Drill-down von Kennzahl zu den zugrunde liegenden Aufträgen.
- Export als CSV und PDF für Geschäftsführung, Teamleitung oder Kundenreview.

## Akzeptanzkriterien

- Jede Kennzahl kann bis auf die zugrunde liegenden Aufträge oder Zeitbuchungen
  nachvollzogen werden.
- Auswertungen unterscheiden zwischen abrechenbarer, nicht abrechenbarer,
  interner, Reise-, Warte- und Nacharbeitszeit.
- Grafiken und Tabellen zeigen denselben Datenstand und denselben Zeitraum.
- Filter nach Zeitraum, Kunde, Projekt, Auftragstyp, Mitarbeitendem, Team,
  Produkt/Dienstmittel und Status sind möglich.
- Auffälligkeiten werden nicht nur gezählt, sondern im Kontext gezeigt:
  absoluter Aufwand, Anteil, Trend und Vergleichswert.

## Datenqualität

Damit Auswertungen belastbar sind, müssen bestimmte Informationen strukturiert
erfasst werden:

- Auftragstyp und Tätigkeit.
- Kunde, Projekt, Aufgabe oder Einsatz.
- Produkt, Anlage, Gerät oder Dienstmittel, soweit relevant.
- Ursache, Fehlerbild oder Problemkategorie.
- Ergebnis: gelöst, offen, Nacharbeit, Eskalation, Kundenrückfrage.
- Abrechenbarkeit und Grund für nicht abrechenbare Zeit.
- Verwendetes Material, Fahrzeug, Werkzeug oder sonstiges Dienstmittel.

## Später

- Zielwerte und Benchmarks pro Auftragstyp oder Kunde.
- Frühwarnungen bei auffälligen Kunden, Produkten oder Dienstmitteln.
- Automatische Handlungsempfehlungen: Schulung, Prozessprüfung, Vertragsprüfung,
  Preisanpassung, Produktproblem an Einkauf/Hersteller melden.
- Kohortenvergleich vor/nach Fortbildung oder Prozessänderung.
- Deckungsbeitragsnahe Auswertung, wenn Kosten- und Erlösdaten vollständig sind.

## Abhängigkeiten

- Aufzeichnung und Zeiterfassung als Kernprodukt
- `Customer`
- `Project`
- `Task`
- `DiaryEntry`
- `TimeEntry`
- `Timesheet`
- `MaterialUsage`
- `Vehicle`
- `TravelLog`
- `Expense`
- `Qualification`
- `AuditLog`
- Reporting-Controller
- Dashboard

## GitHub Issues

- TBD
