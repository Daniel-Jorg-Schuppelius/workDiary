# Systemdienste & Cron einrichten (nach der Web-Installation)

Die Web-Installation richtet die Anwendung ein — für den **vollen Betrieb**
braucht der Server zusätzlich Cron und Dienste. Das erledigt der
Systemdienst-Installer (MVP-454) in einem Schritt:

```bash
cd /pfad/zur/app
sudo scripts/install-system.sh
```

Der Installer ermittelt alles selbst (Installationsverzeichnis aus dem
Skriptpfad, PHP-Binary, Betriebs-User aus dem `storage/`-Owner) und richtet ein:

> **Container-Betrieb?** Für Docker/Compose gilt dieses Handbuch nicht — dort
> übernehmen `compose.yml` (Dienste `queue`, `scheduler`, optional `reverb`)
> und der Entrypoint die Rolle von Cron und systemd:
> [on-premise-docker.md](on-premise-docker.md).

| Komponente                          | Zweck                                                                                                                                              | immer?                   |
| ----------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------ |
| `/etc/cron.d/workdiary`             | `schedule:run` minütlich (Herzschlag ALLER wiederkehrenden Jobs) + tägliches Backup (`scripts/backup.sh`)                                          | ja                       |
| `/etc/workdiary-backup.conf`        | Backup-Konfiguration (Zielverzeichnis, Retention; chmod 600) — eine vorhandene Datei bleibt bei erneutem Lauf erhalten                             | ja (außer `--no-backup`) |
| `BACKUP_HEARTBEAT_TOKEN`            | wird in der App-`.env` erzeugt, falls er fehlt (`artisan workdiary:backup:rotate-token`) — ohne ihn registriert die Statusseite keine Backup-Läufe | ja (außer `--no-backup`) |
| `workdiary-queue.service`           | Queue-Worker — `QUEUE_CONNECTION=database`: ohne ihn bleiben Benachrichtigungen, Importe und Hintergrund-Jobs liegen                               | ja                       |
| `workdiary-reverb.service`          | WebSocket-Server (Chat/Live-Updates, `BROADCAST_CONNECTION=reverb`)                                                                                | `--with-reverb`          |
| `workdiary-integrity-watch.service` | Realtime-Integritätswächter (braucht ext-inotify; der Installer prüft das)                                                                         | `--with-integrity-watch` |
| fail2ban-Filter + -Jails            | OS-seitige IP-Sperren aus dem Security-Log (siehe `deploy/fail2ban/`)                                                                              | `--with-fail2ban`        |

## Optionen

```text
--instance NAME          alle erzeugten Namen instanz-scopen (workdiary-NAME-…),
                         damit mehrere Installationen auf einem Host koexistieren
--with-reverb            Reverb-WebSocket-Dienst mit einrichten
--with-integrity-watch   Realtime-Integritätswächter (ext-inotify nötig)
--with-fail2ban          fail2ban-Filter/-Jails installieren (fail2ban nötig)
--backup-time HH:MM      Backup-Uhrzeit (Default 23:00) — MUSS in der
                         Betriebszeit des Servers liegen (kein Nachholen!)
--backup-dir PFAD        Backup-Zielverzeichnis (Default /var/backups/workdiary)
--backup-keep-days N     Retention in Tagen (Default 14)
--no-backup              keinen Backup-Cron und keine Backup-Konfiguration anlegen
--dry-run                nur zeigen, was geschrieben würde
--status                 Zustand der eingerichteten Komponenten anzeigen
--uninstall              alles wieder entfernen (App/Backups bleiben)
```

Erneutes Ausführen ist gefahrlos (idempotent): Dateien werden überschrieben,
Dienste neu geladen. Ausnahme: eine vorhandene `/etc/workdiary-backup.conf`
bleibt unangetastet — nur explizite `--backup-dir`/`--backup-keep-days`
schreiben sie neu. Overrides per Env: `APP_DIR`, `PHP_BIN`, `RUN_USER`.

## Mehrere Instanzen auf einem Host

Ohne `--instance` verwendet der Installer **feste, systemweite Namen**
(`workdiary-queue.service`, `/etc/cron.d/workdiary`, `/etc/workdiary-backup.conf`,
fail2ban-Jail `[workdiary]`). Eine **zweite** Installation ohne eigenen
`--instance`-Namen überschreibt daher die erste.

Für Parallelbetrieb jede Instanz mit einem eindeutigen `--instance <slug>`
einrichten — dann werden **alle** erzeugten Namen gescoped und koexistieren:

```bash
sudo /pfad/zu/instanzA/scripts/install-system.sh --instance kunde-a
sudo /pfad/zu/instanzB/scripts/install-system.sh --instance kunde-b
```

Ergibt z. B. `workdiary-kunde-a-queue.service`, `/etc/cron.d/workdiary-kunde-a`,
`/etc/workdiary-kunde-a-backup.conf`, Jail `[workdiary-kunde-a]`. Die
fail2ban-**Filter** (`filter.d/workdiary[-strict].conf`) sind geteilt und werden
beim `--uninstall` erst entfernt, wenn keine WorkDiary-Jail mehr existiert.
Status/Deinstallation jeweils mit demselben `--instance` aufrufen.

## Nach der Einrichtung prüfen

```bash
scripts/install-system.sh --status
systemctl status workdiary-queue
php artisan schedule:list          # kein Job darf in einer Abschaltzeit liegen
php artisan workdiary:backup:status         # Backup-Einrichtung mit Handlungshinweisen
php artisan workdiary:backup:rotate-token   # Heartbeat-Token rotieren (angelegt wird er vom Installer)
```

**Server mit Abschaltzeiten** (z. B. nachts aus): Der Laravel-Scheduler holt
verpasste Läufe NICHT nach. Alle täglichen Jobs müssen per Scheduler-Adminseite
(Systembetrieb → Geplante Aufgaben) in die Betriebszeit gelegt werden — ebenso
die Backup-Zeit (`--backup-time`). Details zum Backup: [backup-restore.md](backup-restore.md).

> **Zeitzone:** Alle Uhrzeiten des Zeitplans (Registry-Defaults und Overrides der
> Adminseite) gelten in `SCHEDULE_TIMEZONE` (Standard: `APP_DISPLAY_TIMEZONE`,
> also Europe/Berlin), nicht in UTC. Vor 2026-09 wurden sie in UTC ausgewertet —
> ein „22:10"-Job lief um 00:10 Ortszeit und fiel auf Servern mit nächtlicher
> Abschaltung dauerhaft aus.

## Manuelle Wartungsbefehle

Diese Befehle laufen **nicht** im Zeitplan. Sie sind fuer einmalige
Nachzieh- und Aufraeumarbeiten gedacht und werden von Hand aufgerufen.
Nachgetragen am 2026-09-16 (`MVP-796`, Befund `C1-14`): Sie waren bis dahin
nirgends dokumentiert.

| Befehl | Zweck | Hinweis |
| --- | --- | --- |
| `attendance:backfill` | Erzeugt Anwesenheits-Sitzungen aus vorhandenen Zeiteintraegen. | Nachtrag fuer Bestandsdaten. |
| `billing:reopen-times` | Oeffnet faelschlich als abgerechnet markierte Zeiten ab einem Leistungsdatum wieder. | Rechnungs-, Uebergabe- und saldo-gefuehrte Zeiten bleiben unangetastet. **Ohne `--apply` nur Probelauf.** |
| `billing:sync-entry-billable` | Gleicht das Abrechenbar-Kennzeichen der Zeiteintraege ab. | — |
| `customer:merge-duplicates` | Fuehrt doppelte Kunden zusammen (eindeutige Treffer ueber USt-IdNr. oder Lexoffice-Nummer). | **Ohne `--apply` nur Vorschau.** |
| `project:merge-duplicates` | Fuehrt doppelte Projekte zusammen (gleicher Kunde und Name). | **Ohne `--apply` nur Vorschau.** |
| `geocode:customers` | Traegt fehlende Koordinaten zu Kundenadressen nach (Nominatim). | Externer Dienst, Ratenlimit beachten. |
| `whistleblowing:seed-roles` | Legt Meldestellen-Rolle und Rechte fuer bestehende Organisationen an. | Nachtrag fuer Mandanten, die vor dem Modul angelegt wurden. |

> **Vor jedem Lauf mit `--apply`:** erst ohne den Schalter starten und die
> Vorschau lesen. Zusammenfuehrungen sind nicht umkehrbar.
