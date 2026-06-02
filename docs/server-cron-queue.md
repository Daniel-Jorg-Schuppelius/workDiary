# Server-Betrieb: Cronjob &amp; Queue-Worker (selbst-gehostete Installation)

Status: Aktiv • Zielgruppe: Betreiber einer selbst-gehosteten
WorkDiary-Installation (Linux, inkl. Shared-Hosting/ISPConfig).

Nach Abschluss des Web-Installers muss auf dem Server **ein Cronjob** für
den Laravel-Scheduler eingerichtet werden. Wird zusätzlich eine
datenbankbasierte Queue genutzt (`QUEUE_CONNECTION=database`, Standard),
muss auch ein Queue-Worker laufen.

## 0. Schnellstart: ein einziger Cron-Eintrag (empfohlen)

Das mitgelieferte Script `scripts/cron.sh` fasst Scheduler **und** einen
kurzen Queue-Durchlauf zusammen — so genügt **ein** Cron-Eintrag:

```cron
* * * * * /var/www/clients/client1/web141/web/scripts/cron.sh >> /dev/null 2>&1
```

Das Script ermittelt das Installationsverzeichnis selbst (eine Ebene über
`scripts/`). Optionale Env-Variablen:

| Variable         | Default | Zweck                                    |
| ---------------- | ------- | ---------------------------------------- |
| `APP_DIR`        | autom.  | WorkDiary-Verzeichnis (falls abweichend) |
| `PHP_BIN`        | `php`   | PHP-Interpreter (z. B. `php8.4`)         |
| `RUN_QUEUE`      | `1`     | `0` = Queue überspringen (bei `sync`)    |
| `QUEUE_MAX_TIME` | `55`    | Sekunden Queue-Arbeit pro Lauf           |

Beispiel mit explizitem PHP-Binary in ISPConfig:

```cron
* * * * * PHP_BIN=php8.4 /var/www/clients/client1/web141/web/scripts/cron.sh >> /dev/null 2>&1
```

Wer Scheduler und Queue lieber getrennt steuert, nutzt die Einzeleinträge
aus §1 und §2.

## 1. Scheduler-Cronjob (Pflicht)

Der Scheduler steuert u.a. Archivierung, Anwesenheits-Abschluss,
Veranstaltungs-Erinnerungen, Zertifikatsprüfungen, Plugin-Healthcheck,
Backup-Check, Wartungs- und SLA-Scans (siehe `routes/console.php`).

Ein einziger Cron-Eintrag genügt — er ruft minütlich `schedule:run`, das
intern entscheidet, welche Aufgaben fällig sind:

```cron
* * * * * cd /var/www/clients/client1/web141/web &amp;&amp; php artisan schedule:run >> /dev/null 2>&1
```

> Pfad ggf. an die tatsächliche Web-Root anpassen. In ISPConfig kann der
> Eintrag unter „Sites → (Domain) → Cron" als „voller Cron" angelegt
> werden.

Die im Scheduler verwendete Option `->onOneServer()` benötigt einen
funktionierenden **Cache** (bei `CACHE_STORE=database` muss die
`cache`-Tabelle existieren), damit das Locking greift.

## 2. Queue-Worker (bei `QUEUE_CONNECTION=database`)

Hintergrund-Jobs (Mails, Web-Push, Exporte) werden nur abgearbeitet,
wenn ein Worker läuft.

### Variante A — Cron (Shared-Hosting/ISPConfig, kein Supervisor)

Arbeitet die Queue jede Minute kurz ab und beendet sich selbst:

```cron
* * * * * cd /var/www/clients/client1/web141/web &amp;&amp; php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

### Variante B — Supervisor/systemd (dedizierter Server, empfohlen)

```ini
# /etc/supervisor/conf.d/workdiary-worker.conf
[program:workdiary-worker]
command=php /var/www/clients/client1/web141/web/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=web141
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
```

### Keine Queue gewünscht

Alternativ `QUEUE_CONNECTION=sync` in der `.env` setzen — Jobs laufen
dann synchron im Request. Für produktive Installationen nicht empfohlen
(längere Antwortzeiten, kein Retry).

## 3. Nach Konfigurationsänderungen

Wird die Konfiguration gecacht (`php artisan config:cache`), nach jeder
`.env`-Änderung neu bauen bzw. Worker neu starten:

```cron
php artisan config:clear   # oder: php artisan config:cache
php artisan queue:restart  # laufende Worker übernehmen neue Einstellungen
```

## 4. Funktionsprüfung

- Letzter Scheduler-Lauf ist auf der Diagnose-Seite als Heartbeat
  sichtbar (siehe [docs/diagnose-seite.md](diagnose-seite.md)).
- Manuell testen: `php artisan schedule:run` bzw.
  `php artisan queue:work --once`.
