# Offline-Sync und Konfliktlösung

## Status

Proposed — Architekturkonzept erstellt (2026-06-10):
[Offline-Sync-Architektur](../offline-sync-architektur.md). Umsetzung
bewusst zurückgestellt bis zur CSP-/Alpine-Build-Entscheidung.

## Ziel

WorkDiary soll offline erfasste Daten zuverlässig synchronisieren und Konflikte
verständlich lösen. Das betrifft mobile Aufträge, Protokolle, Fotos,
Unterschriften, Zeiten, Material und offene Punkte.

## Warum

Offlinefähigkeit ist nur brauchbar, wenn Konflikte nicht still Daten zerstören.
Gerade bei abgenommenen Protokollen, paralleler Bearbeitung oder verzögerten
Fotos muss der Sync transparent und überprüfbar sein.

## MVP

- Lokale Sync-Queue mit Status pro Datensatz.
- Konflikterkennung bei parallelen Änderungen.
- Konfliktansicht mit Serverstand, lokalem Stand und Entscheidung.
- Schutz abgenommener oder gesperrter Protokolle.
- Wiederholbarer Upload für Fotos und Anhänge.
- Audit-Log für Sync-Konflikte und Auflösungen.

## Akzeptanzkriterien

- Offline-Daten gehen bei Netzwechsel nicht verloren.
- Konflikte werden sichtbar statt still überschrieben.
- Nutzer erkennen, was synchronisiert, offen oder fehlgeschlagen ist.
- Abnahmen und Signaturen bleiben nachvollziehbar.

## Abhängigkeiten

- Mobiler Field-Workflow
- Dokumentation und Abnahmeprotokolle
- Datenschutz, Sicherheit und Datenlebenszyklus
- PWA
- Audit

## GitHub Issues

- TBD
