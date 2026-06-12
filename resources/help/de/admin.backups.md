---
title: "Backups & Betriebsüberwachung"
topic: admin.backups
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.security
---

WorkDiary überwacht externe Backups über einen **Heartbeat**: Dein
Backup-Job meldet nach jedem Lauf den Erfolg an die Plattform.

So funktioniert der Heartbeat:

- Endpoint: `POST /admin/backup/heartbeat`, authentifiziert per
  Bearer-Token (außerhalb des normalen Login-Stacks, gedrosselt).
- Übermittelt werden u. a. **Manifest-Hash (SHA-256)**, **Größe**,
  Quelle und Zeitpunkt.
- Jeder Eingang wird gespeichert und als Audit-Event
  `backup.heartbeatReceived` protokolliert.

Aktueller Stand (ehrlich): Es gibt **noch kein Monitoring-UI** – die
Kontrolle läuft über die Heartbeat-Tabelle und den Audit-Trail.
Richte dir daher eine externe Alarmierung ein, wenn Heartbeats
ausbleiben.

Systemzustand: Der Befehl `php artisan system:health` prüft
Datenbankverbindung, Migrationen, Storage (Lese-/Schreibtest), Queue,
APP_KEY, Mail und Lizenz – Exit-Code 0 bei gesund, 1 bei Fehlern.
Er ändert keine Daten und eignet sich für Cron/CI.

Wichtig für die Wiederherstellung:

- Sichere neben der Datenbank unbedingt den **APP_KEY** – ohne ihn
  sind verschlüsselte Felder (PII, 2FA, Datenschutz-Fälle) verloren.
- Teste Restores regelmäßig auf einer separaten Umgebung.
