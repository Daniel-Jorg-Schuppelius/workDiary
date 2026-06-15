# Backup, Restore und Disaster Recovery

## Status

In Progress — Backup-/Restore-Konzept für lokale Installationen liegt vor in
[`docs/backup-restore.md`](../backup-restore.md) (MVP-046, Issue #45).
MVP-Erweiterung umgesetzt: Admin-Backup-Statusseite (`admin/backup`,
Permission `backup.view`) mit letzter Sicherung je Quelle + Frische-/Ausfall-
Warnung (Schwelle `backup.heartbeat_freshness_hours`, Default 26 h),
Restore-Test-Register (`restore_tests`, plattformweit) mit
„Restore-Test protokollieren"-Modal und Überfälligkeits-Warnung (Default
180 Tage). Warnungen zusätzlich als `system:health`-Checks (Backup-Heartbeat
+ Restore-Test). Bewusst offen: automatisierte Restore-AUSFÜHRUNG (das Register
dokumentiert manuell/Skript-durchgeführte Tests), SaaS-mandantenbezogenes
Restore, eingebautes Backup-Tool — siehe `docs/backup-restore.md §9`.

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
