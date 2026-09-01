---
title: "Backups & Betriebsüberwachung"
topic: admin.backups
version: 3
audience:
    - admin
schema: process
related:
    - admin.handbook
    - admin.security
    - admin.import
---

## Zweck und Hintergrund

WorkDiary überwacht externe Backups über einen **Heartbeat**: Der
Backup-Job meldet nach jedem Lauf seinen Erfolg an die Plattform.
Backups werden nicht manuell registriert — mit dem ersten Heartbeat
erscheint die Quelle automatisch auf **Backup & Restore**. Die
eigentliche Sicherung und Wiederherstellung laufen bewusst außerhalb
von WorkDiary.

## Voraussetzungen

- Ein externer Backup-Job (z. B. das mitgelieferte `backup.sh`).
- Der Heartbeat-Token in der Umgebungsvariable
  `BACKUP_HEARTBEAT_TOKEN` — ohne Token ist der Endpoint deaktiviert.
- Administrationsrechte für die Seite **Backup & Restore**.

## Empfohlener Ablauf

1. Backup-Job einrichten und den Heartbeat senden lassen:
   `POST /admin/backup/heartbeat` mit Bearer-Token (außerhalb des
   Login-Stacks, gedrosselt); übermittelt werden `manifest_sha256`,
   `size_bytes`, `source` und `occurred_at`.
2. Auf **Backup & Restore** die Quelle prüfen: Die Seite zeigt je
   Quelle die letzte Sicherung und markiert sie als **überfällig**,
   wenn der jüngste Heartbeat älter ist als die konfigurierte Frische
   (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`, Standard 26 h).
3. Restore-Tests regelmäßig auf einer separaten Umgebung durchführen
   und über **Restore-Test protokollieren** ins Register eintragen.
4. Systemzustand automatisiert prüfen: `php artisan system:health`
   testet Datenbank, Migrationen, Storage, Queue, APP_KEY, Mail und
   Lizenz (Exit-Code 0/1, ändert keine Daten — ideal für Cron/CI).

## Beispiel aus der Praxis

Der nächtliche Cron sichert um 23 Uhr und meldet den Heartbeat. Als
ein Storage-Umbau den Job zwei Nächte stoppt, springt die Quelle auf
„überfällig" — der Admin sieht es morgens auf dem Dashboard, bevor
Datenverlust droht.

## Typische Fehler

- **Nur die Datenbank sichern:** Ohne den **APP_KEY** sind
  verschlüsselte Felder (PII, 2FA, Datenschutz-Fälle) unwiederbringlich
  verloren.
- **Restores nie testen:** Ein Backup ohne geprüften Restore ist eine
  Hoffnung, kein Konzept.
- **Heartbeat mit Backup verwechseln:** Der Heartbeat meldet nur den
  Erfolg — er ersetzt weder Sicherung noch Aufbewahrung.

## Auswirkungen und nächste Schritte

Jeder Heartbeat wird gespeichert und als Audit-Event
`backup.heartbeatReceived` protokolliert; Überfälligkeit erscheint in
der Betriebsüberwachung. Als Nächstes: Restore-Test terminieren,
`system:health` in den Cron aufnehmen und die Hinweise im
Admin-Handbuch zur Notfallwiederherstellung lesen.
