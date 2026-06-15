# Produkt-/Objektakte und Lebenszyklus

## Status

In Progress — Kern-MVP umgesetzt; konzipiert in MVP-035, 036, 037:
[Asset-Stammdaten](../asset-stammdaten.md),
[Asset-Verknüpfungen](../asset-verknuepfungen.md),
[Objekt-Timeline](../objekt-timeline.md).

Umgesetzt (2026-06): **Objektakte / Lebenszyklus-Dossier** als druckbare
Gesamtsicht je Asset (`assets.dossier`, Pendant zur Auftrags-Fallakte
`diary/case-file`, Print-CSS, `?print=1`) — baut auf `AssetTimelineService`
(additiv um Ausgabe/Rückgabe, Defekte und durchgeführte Wartungen erweitert)
sowie den 009-Modellen `AssetAssignment`/`AssetDefect`/`MaintenancePlan` auf.
**Lebenszyklus-Status** (in Betrieb / ersetzt / stillgelegt) wird über
`AssetLifecycleService` aus `status` + `decommissioned_on` abgeleitet (keine
neue Statusmaschine) und im Kopf der Asset-Seite und der Objektakte angezeigt.
**Raumbezogene Anforderungen** je Gewerk über eigene 1:n-Tabelle
`room_requirements` (kind/level/note; Hygienestufe, Sonderreinigung,
Zugangsbeschränkung, IT-Inventar, technische Prüfung, Betreiberpflicht) —
ergänzend zum `CleaningProfile`, gepflegt in der Raumverwaltung, sichtbar in
Raumliste, Asset-Seite und Objektakte.

Offen (Folge): SLA/Vertrags-Verknüpfung, Eigentümerwechsel-Historie als
eigene Sektion, Customer-Portal-Sicht der Objektakte.

## Ziel

WorkDiary soll für Produkte, Anlagen, Baustellen, Gebäude, Bereiche, Räume,
Geräte oder sonstige Objekte eine eigene Lebensakte führen. Diese Akte bündelt
Einbau, Standort, Eigentümer, Wartungen, Störungen, Updates, Ersatzteile,
Dokumente, Protokolle, Seriennummern, Eigentümerwechsel und Stilllegung über
den gesamten Lebenszyklus.

## Warum

Viele Aufträge beziehen sich nicht nur auf einen Kunden, sondern auf ein
konkretes Objekt. Ohne Objektakte gehen Historie, wiederkehrende Probleme,
Wartungspflichten und technische Änderungen verloren.

Im Facility-Management-Kontext ist der Objektbezug oft ein Gebäude, eine Etage,
ein Raum oder ein Außenbereich. Verschiedene Gewerke betrachten denselben Raum
unterschiedlich: Reinigung braucht z. B. Hygiene- und Sonderreinigungsmerkmale,
IT-Service braucht Kundenrechner, Drucker oder Netzwerkkomponenten, Hausmeister
brauchen Mängel, Schlüssel, Zählerstände und Betreiberpflichten.

## MVP

- Objektstammdaten mit Typ, Standort, Gebäude/Bereich/Raum, Kunde,
  Seriennummer und Status.
- Gebäude, Etagen, Räume und Außenbereiche können als Objekte geführt oder als
  strukturierter Standort für andere Assets genutzt werden.
- Timeline aller Aufträge, Protokolle, Wartungen, Updates und Dokumente.
- Verknüpfung mit Assets, Materialien, Fotos, Messwerten und Abnahmen.
- Raumbezogene Anforderungen je Gewerk, z. B. Sonderreinigung,
  Hygienestufe, Zugangsbeschränkung, IT-Inventar, technische Prüfung oder
  Betreiberpflicht.
- Wartungs- und Prüfintervalle pro Objekt.
- Status: aktiv, in Wartung, gesperrt, ersetzt, stillgelegt.

## Akzeptanzkriterien

- Ein Objekt zeigt seine vollständige Historie.
- Ein Raum oder Gebäudebereich kann mehrere fachliche Anforderungen tragen,
  ohne doppelt angelegt zu werden.
- Kundenrechner, Reinigungsanforderungen, technische Anlagen und Mängel können
  einem Raum oder Bereich zugeordnet werden.
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
