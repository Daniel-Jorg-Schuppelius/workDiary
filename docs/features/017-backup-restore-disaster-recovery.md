# Backup, Restore und Disaster Recovery

## Status

Proposed — Backup-/Restore-Konzept für lokale Installationen liegt vor in
[`docs/backup-restore.md`](../backup-restore.md) (MVP-046, Issue #45).

## Ziel

WorkDiary soll für lokale Installationen und SaaS verlässliche Sicherungs- und
Wiederherstellungsprozesse definieren. Datenbank, Anhänge, Exporte, Lizenzen,
Konfigurationen und Mandantendaten müssen konsistent gesichert und getestet
wiederherstellbar sein.

## Warum

Ein Nachweissystem ist nur vertrauenswürdig, wenn Datenverlust beherrscht wird.
Für Kunden ist entscheidend, ob Auftragsdokumentation, Arbeitszeiten,
Unterschriften, Fotos und Rechnungsgrundlagen im Ernstfall wiederhergestellt
werden können.

## MVP

- Backup-Konzept für DB, Storage, Lizenzdateien und Konfiguration.
- Restore-Anleitung für lokale Installationen.
- Mandantenbezogenes Restore-Konzept für SaaS.
- Backup-Status in der Admin-Oberfläche.
- Regelmäßige Restore-Tests dokumentieren.
- Warnungen bei fehlgeschlagenen oder veralteten Backups.

## Akzeptanzkriterien

- Ein Backup kann vollständig wiederhergestellt werden.
- Anhänge und Datenbank bleiben konsistent.
- SaaS-Restore beschädigt keine anderen Mandanten.
- Admins sehen letzte erfolgreiche Sicherung und Fehler.
- Restore-Prozesse sind dokumentiert und versioniert.

## Abhängigkeiten

- Mandantenfähigkeit und Betriebsmodelle
- Datenschutz, Sicherheit und Datenlebenszyklus
- Anhänge und Storage
- Deployment

## GitHub Issues

- TBD
