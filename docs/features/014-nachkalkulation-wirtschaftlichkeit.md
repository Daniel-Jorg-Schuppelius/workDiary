# Nachkalkulation und Wirtschaftlichkeit

## Status

Proposed — Plan/Ist-Abgleich (MVP-018):
[docs/plan-ist-abgleich.md](../plan-ist-abgleich.md). Wirtschaftlichkeit
(€-Plan/Ist) folgt in späteren MVPs.

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
- Export für Geschäftsführung oder Controlling.

## Akzeptanzkriterien

- Ein Auftrag zeigt kalkulierten, erfassten und abgerechneten Aufwand.
- Nicht abrechenbare Zeiten haben einen Grund.
- Wiederkehrend defizitäre Kunden, Produkte oder Auftragstypen werden sichtbar.
- Nachkalkulationen können bis auf einzelne Zeit-, Material- und Belegpositionen
  zurückverfolgt werden.

## Abhängigkeiten

- Auswertungen und Entscheidungsgrundlagen
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
