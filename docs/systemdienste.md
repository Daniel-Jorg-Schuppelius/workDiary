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

| Komponente | Zweck | immer? |
| --- | --- | --- |
| `/etc/cron.d/workdiary` | `schedule:run` minütlich (Herzschlag ALLER wiederkehrenden Jobs) + tägliches Backup (`scripts/backup.sh`) | ja |
| `workdiary-queue.service` | Queue-Worker — `QUEUE_CONNECTION=database`: ohne ihn bleiben Benachrichtigungen, Importe und Hintergrund-Jobs liegen | ja |
| `workdiary-reverb.service` | WebSocket-Server (Chat/Live-Updates, `BROADCAST_CONNECTION=reverb`) | `--with-reverb` |
| `workdiary-integrity-watch.service` | Realtime-Integritätswächter (braucht ext-inotify; der Installer prüft das) | `--with-integrity-watch` |
| fail2ban-Filter + -Jails | OS-seitige IP-Sperren aus dem Security-Log (siehe `deploy/fail2ban/`) | `--with-fail2ban` |

## Optionen

```text
--with-reverb            Reverb-WebSocket-Dienst mit einrichten
--with-integrity-watch   Realtime-Integritätswächter (ext-inotify nötig)
--with-fail2ban          fail2ban-Filter/-Jails installieren (fail2ban nötig)
--backup-time HH:MM      Backup-Uhrzeit (Default 23:00) — MUSS in der
                         Betriebszeit des Servers liegen (kein Nachholen!)
--no-backup              keinen Backup-Cron eintragen
--dry-run                nur zeigen, was geschrieben würde
--status                 Zustand der eingerichteten Komponenten anzeigen
--uninstall              alles wieder entfernen (App/Backups bleiben)
```

Erneutes Ausführen ist gefahrlos (idempotent): Dateien werden überschrieben,
Dienste neu geladen. Overrides per Env: `APP_DIR`, `PHP_BIN`, `RUN_USER`.

## Nach der Einrichtung prüfen

```bash
scripts/install-system.sh --status
systemctl status workdiary-queue
php artisan schedule:list          # kein Job darf in einer Abschaltzeit liegen
php artisan workdiary:backup:rotate-token   # Heartbeat-Token für die Backup-Statusseite
```

**Server mit Abschaltzeiten** (z. B. nachts aus): Der Laravel-Scheduler holt
verpasste Läufe NICHT nach. Alle täglichen Jobs müssen per Scheduler-Adminseite
(Systembetrieb → Geplante Aufgaben) in die Betriebszeit gelegt werden — ebenso
die Backup-Zeit (`--backup-time`). Details zum Backup: [backup-restore.md](backup-restore.md).
