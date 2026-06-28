# Nachkalkulation und Wirtschaftlichkeit

## Status

In Progress — Plan/Ist-Abgleich (MVP-018):
[docs/plan-ist-abgleich.md](../plan-ist-abgleich.md).

Wirtschaftlichkeits-/Deckungsbeitrags-Report umgesetzt
(`reports.economics`, Geschäftsführung/Buchhaltung, Plan-Gating
`module.auswertungen_team`): Erlös (abrechenbare Zeiten × Satz + abgerechnetes
Material + abrechenbare Spesen) vs. Kosten (interner Zeit-Kostensatz
`TimeEntry.internal_rate` + Material-/Beleg-Direktaufwand) ⇒ Deckungsbeitrag
absolut und als Marge, je Kunde und je Projekt; Top/Flop-Ranking; nicht
abrechenbare Zeit (`billable=false`) als Nacharbeits-/Kulanz-Proxy; Plan-vs-Ist
in Minuten (`Project.time_budget`) und in Geld (`Project.budget`). CSV/PDF wie
die übrigen Reports. Maßgebliche Rechnungen führt das externe Faktura-System;
die Werte hier sind Projektion. Offen: dedizierter Nacharbeit-/Kulanz-Typ,
Belegtiefe-Drilldown, separater Materialkostensatz.

## Ziel

WorkDiary soll Firmen helfen, Aufträge, Kunden, Produkte und Leistungen
wirtschaftlich zu bewerten. Aus aufgezeichneten Zeiten, Material, Fahrten,
Spesen, Zuschlägen und Rechnungen sollen Nachkalkulationen entstehen.

## Warum

Ein Auftrag kann fachlich korrekt abgeschlossen sein und trotzdem wirtschaftlich
schlecht laufen. Ohne Nachkalkulation sieht eine Firma nicht, ob kalkulierte
Stunden realistisch waren, welche Kunden Nacharbeit erzeugen, welche Leistungen
zu billig angeboten werden oder welche Produktprobleme Marge zerstören.

## MVP

- Plan/Ist-Vergleich für Zeit, Material, Fahrt und Spesen.
- Abrechenbare und nicht abrechenbare Aufwände getrennt ausweisen.
- Deckungsbeitragsnahe Übersicht, soweit Kosten- und Erlösdaten vorhanden sind.
- Kunden-, Projekt- und Auftragstyp-Ranking nach Wirtschaftlichkeit.
- Nacharbeits- und Kulanzzeiten sichtbar machen.
- Bei Bau-/Ausbauprojekten optionale Auswertung je LV-Position,
  Ordnungszahl, Aufmaß-Stand und Nachtrag, sobald GAEB/LV-Daten vorhanden
  sind.
- Export für Geschäftsführung oder Controlling.

## Akzeptanzkriterien

- Ein Auftrag zeigt kalkulierten, erfassten und abgerechneten Aufwand.
- Bei GAEB-geführten Projekten kann der Aufwand bis zur LV-Position und zum
  Nachtrag zurückverfolgt werden.
- Nicht abrechenbare Zeiten haben einen Grund.
- Wiederkehrend defizitäre Kunden, Produkte oder Auftragstypen werden sichtbar.
- Nachkalkulationen können bis auf einzelne Zeit-, Material- und Belegpositionen
  zurückverfolgt werden.

## Abhängigkeiten

- Auswertungen und Entscheidungsgrundlagen
- GAEB-Leistungsverzeichnisse und AVA-Austausch
- Lohn, Zuschläge und DATEV/Lexware
- SLA, Verträge und Service-Level
- `TimeEntry`
- `MaterialUsage`
- `TravelLog`
- `Expense`
- `Invoice`
- `Project`
- `Customer`

## GitHub Issues

- TBD
