# Karten, Standort und Leitstelle

## Status

In Progress — MVP-Leitstelle umgesetzt: Dispatch-Board (Spalten nach
Dispositionsstatus / Bahnen nach Mitarbeiter) und Karten-Sicht mit
SLA-Risiko-Filter (route `dispatch.board` / `dispatch.map`, Recht
`dispatch.viewAny`, Plan-Gating `module.planung`). Wiederverwendet
Dispositionsstatus (Feature 028), Konfliktprüfung (DispatchConflictChecker),
SLA-Status der Service-Tickets (Feature 010) und die bestehende
Leaflet-/map.js-Einbindung. Bewusst NICHT enthalten (Datenschutz):
Tourenoptimierung, Echtzeit-Tracking, dauerhafte Standortüberwachung.

## Ziel

WorkDiary soll operative Arbeit räumlich sichtbar machen: Kunden, Baustellen,
Objekte, Fahrzeuge, Touren, offene Einsätze, SLA-Risiken und geplante Termine
sollen auf einer Karte oder Leitstellenansicht dargestellt werden können.

## Warum

Für Außendienst und Service ist Standort entscheidend. Karten helfen bei
Disposition, Touren, Notfällen, Fahrzeitbewertung und Erkennung regionaler
Problemcluster.

## MVP

- Kartenansicht für Kunden, Objekte, Aufträge und Touren.
- Filter nach Status, Zeitraum, Team, SLA und Priorität.
- Anzeige geplanter und offener Einsätze.
- Verknüpfung zur Tourenoptimierung.
- Datenschutzkonforme Standortoptionen, keine dauerhafte Überwachung ohne
  klare Aktivierung und Zweckbindung.

## Akzeptanzkriterien

- Leitstelle sieht relevante Einsätze räumlich.
- Standortdaten respektieren Rechte, Mandant und Datenschutz.
- Kartenpunkte führen zur Fallakte oder zum Auftrag.
- Fahrzeit und Entfernung können in Planung und Auswertung einfließen.

## Abhängigkeiten

- Datenschutz, Sicherheit und Datenlebenszyklus
- Terminierung, Einsatzplanung und Disposition
- Tourenoptimierung
- Kunden und Objekte

## GitHub Issues

- TBD
