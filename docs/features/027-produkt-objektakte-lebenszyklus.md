# Produkt-/Objektakte und Lebenszyklus

## Status

Proposed — Konzipiert in MVP-035, 036, 037:
[Asset-Stammdaten](../asset-stammdaten.md),
[Asset-Verknüpfungen](../asset-verknuepfungen.md),
[Objekt-Timeline](../objekt-timeline.md).

## Ziel

WorkDiary soll für Produkte, Anlagen, Baustellen, Geräte oder sonstige Objekte
eine eigene Lebensakte führen. Diese Akte bündelt Einbau, Standort, Eigentümer,
Wartungen, Störungen, Updates, Ersatzteile, Dokumente, Protokolle,
Seriennummern, Eigentümerwechsel und Stilllegung über den gesamten Lebenszyklus.

## Warum

Viele Aufträge beziehen sich nicht nur auf einen Kunden, sondern auf ein
konkretes Objekt. Ohne Objektakte gehen Historie, wiederkehrende Probleme,
Wartungspflichten und technische Änderungen verloren.

## MVP

- Objektstammdaten mit Typ, Standort, Kunde, Seriennummer und Status.
- Timeline aller Aufträge, Protokolle, Wartungen, Updates und Dokumente.
- Verknüpfung mit Assets, Materialien, Fotos, Messwerten und Abnahmen.
- Wartungs- und Prüfintervalle pro Objekt.
- Status: aktiv, in Wartung, gesperrt, ersetzt, stillgelegt.

## Akzeptanzkriterien

- Ein Objekt zeigt seine vollständige Historie.
- Wiederkehrende Probleme sind am Objekt sichtbar.
- Aufträge können einem Objekt eindeutig zugeordnet werden.
- Stillgelegte oder gesperrte Objekte bleiben historisch auswertbar.

## Abhängigkeiten

- Dokumentation und Abnahmeprotokolle
- Inventar, Dienstmittel und Assets
- Suche, Timeline und Fallakte
- SLA, Verträge und Service-Level

## GitHub Issues

- TBD
