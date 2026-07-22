---
title: "Backups & Betriebsüberwachung"
topic: admin.backups
version: 2
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary überwacht externe Backups über einen **Heartbeat**: Dein
Backup-Job meldet nach jedem Lauf den Erfolg an die Plattform. Backups
werden also nicht manuell in der Oberfläche registriert — sobald der
erste Heartbeat eingeht, erscheint die Quelle automatisch auf der Seite
**Backup & Restore**.

So funktioniert der Heartbeat:

- Endpoint: `POST /admin/backup/heartbeat`, authentifiziert per
  Bearer-Token (außerhalb des normalen Login-Stacks, gedrosselt).
- Der Token wird über die Umgebungsvariable `BACKUP_HEARTBEAT_TOKEN`
  gesetzt; ohne gesetzten Token ist der Endpoint deaktiviert.
- Übermittelt werden `manifest_sha256` (SHA-256), `size_bytes`,
  `source` und `occurred_at`.
- Jeder Eingang wird gespeichert und als Audit-Event
  `backup.heartbeatReceived` protokolliert.

Die Seite **Backup & Restore** zeigt je Quelle die letzte Sicherung und
markiert sie als überfällig, wenn der jüngste Heartbeat älter als die
konfigurierte Frische ist (`BACKUP_HEARTBEAT_FRESHNESS_HOURS`,
Standard 26 h). Restore-Tests trägst Du dort über
**Restore-Test protokollieren** ins Register ein; die eigentliche
Wiederherstellung läuft bewusst außerhalb von WorkDiary.

Systemzustand: Der Befehl `php artisan system:health` prüft
Datenbankverbindung, Migrationen, Storage (Lese-/Schreibtest), Queue,
APP_KEY, Mail und Lizenz – Exit-Code 0 bei gesund, 1 bei Fehlern.
Er ändert keine Daten und eignet sich für Cron/CI.

Wichtig für die Wiederherstellung:

- Sichere neben der Datenbank unbedingt den **APP_KEY** – ohne ihn
  sind verschlüsselte Felder (PII, 2FA, Datenschutz-Fälle) verloren.
- Teste Restores regelmäßig auf einer separaten Umgebung.
