# On-Premise-Betrieb mit Docker (Single-Host)

Betreiber-Handbuch für die Container-Distribution (MVP-720, Feature 015).
Ausgeliefert werden ein Multi-Stage-`Dockerfile`, eine `compose.yml` für den
Betrieb auf **einem** Host und die ENV-Vorlage `.env.docker.example`. Der
Bare-Metal-Weg (Web-Installer + `scripts/install-system.sh`) bleibt
unverändert und ist in [systemdienste.md](systemdienste.md) beschrieben.

| Datei                        | Zweck                                                                                          |
| ---------------------------- | ---------------------------------------------------------------------------------------------- |
| `Dockerfile`                 | Stages `base` → `vendor` (Composer ohne Dev) → `assets` (Vite) → `web` (nginx) → `app` (php-fpm) |
| `compose.yml`                | Dienste `app`, `web`, `db` (MariaDB 11), `queue`, `scheduler`; Profile `redis`, `reverb`      |
| `.env.docker.example`        | Vorlage für `.env.docker` — im Container gibt es **keine** `.env`-Datei, alles kommt per ENV   |
| `deploy/docker/nginx.conf`   | vhost des `web`-Dienstes (HTTP nach innen, TLS beim vorgelagerten Proxy)                       |
| `deploy/docker/entrypoint.sh` | Startlogik: APP_KEY-Pflicht, DB-Wartezeit, Opt-in-Migration, Caches                           |
| `deploy/docker/healthcheck.sh` | FastCGI-Aufruf von `/up` (Laravel-Health-Route) für den php-fpm-Container                    |
| `deploy/docker/php.ini`, `php-fpm.conf` | Upload-Grenzen (64 MB), OPcache, Pool-Größe                                        |

## 1. Voraussetzungen

- Docker Engine ≥ 24 mit Compose v2 (`docker compose version`), x86_64 oder arm64.
- Richtwert Host: 2 vCPU, 4 GB RAM, 20 GB SSD (Image ≈ 1,2 GB mit OCR-Stack,
  Datenbank + Uploads wachsen mit der Nutzung). Siehe Abschnitt 8.
- Ein vorgelagerter Reverse-Proxy mit TLS (Traefik, Caddy, nginx, Apache) —
  der `web`-Dienst spricht nur HTTP. Ohne HTTPS muss `SESSION_SECURE_COOKIE=false`
  gesetzt werden, sonst ist kein Login möglich (nur für Tests vertretbar).
- Für den Image-Build: ein **sauberer Checkout** des gewünschten Release-Tags.
  `composer.local.json` (private Pakete) ist vom Build ausgeschlossen; wer
  private Pakete braucht, gibt `--secret id=composer_auth,src=$HOME/.composer/auth.json`
  mit. Es werden keine fertigen Images veröffentlicht — der CI-Job `docker-build`
  baut nur zur Prüfung (Push nach GHCR ist dokumentiert, aber deaktiviert).

## 2. Was im Image steckt

PHP 8.4 (php-fpm, Debian bookworm) mit den Extensions aus `composer.json`
der Toolkits und dem Laravel-Standard: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`,
`intl`, `gd`, `zip`, `bcmath`, `sodium`, `opcache`, `pcntl`, `exif`, `redis`.
`ext-inotify` (Realtime-Integritätswächter) ist optional (`WD_WITH_INOTIFY=1`).

System-Binaries werden **aus der CommandBuilder-Konfiguration der Toolkits**
abgeleitet (`vendor/dschuppelius/php-common-toolkit/config/*_executables.json`,
`vendor/daniel-jorg-schuppelius/php-pdf-toolkit/config/executables.json`),
nicht geraten. Fehlt ein Binary, deaktiviert der ConfigToolkit nur die
abhängige Funktion — die App startet trotzdem.

| Paket (Debian)                                   | Quelle                                                              | im Image |
| ------------------------------------------------ | ------------------------------------------------------------------- | -------- |
| `poppler-utils`                                  | `pdf_executables.json` (pdfinfo), pdf-toolkit (pdftotext, pdftoppm, pdfdetach, pdfunite) | ja |
| `mupdf-tools`                                    | `pdf_executables.json` valid-pdf (mutool)                           | ja       |
| `qpdf`                                           | `pdf_executables.json` pdf-decrypt/-encrypt/-check, pdf-toolkit Rotation | ja  |
| `imagemagick`                                    | `image_executables.json`, `tiff_executables.json`, pdf-toolkit Deskew | ja     |
| `libtiff-tools`                                  | `tiff_executables.json` (tiff2pdf, tiffcp, tiffinfo)                | ja       |
| `ghostscript`                                    | pdf-toolkit gs (Crop/Resize), Abhängigkeit von ocrmypdf             | ja       |
| `tesseract-ocr` + `-deu` + `-eng`               | pdf-toolkit tesseract, `settings.json` `tesseract_lang = deu+eng`   | ja       |
| `ocrmypdf`                                       | pdf-toolkit ocrmypdf                                                | ja       |
| `file`                                           | `common_executables.json` mimetype/mime-encoding                    | ja       |
| `mariadb-client`, `sqlite3`                      | `scripts/backup.sh`, `BackupSnapshotBuilder` (mysqldump), SQLite-Online-Backup | ja |
| `catdoc`                                         | `InvoicePdfImportService` (Legacy-`.doc`, optional)                 | ja       |
| `libfcgi-bin`, `curl`, `ca-certificates`, `tzdata` | Healthcheck, HTTPS-Integrationen, Zeitzonen                       | ja       |
| `default-jre-headless`, `pdftk-java`             | `common_executables.json` java/java-program (KoSIT-Validator, PDFBox), pdf-toolkit pdftk | `WD_WITH_JAVA=1` |
| `libreoffice-writer/-calc/-impress`              | `office_executables.json` (Office → PDF)                            | `WD_WITH_LIBREOFFICE=1` |
| nicht enthalten                                  | `media_executables.json` (ffmpeg, whisper, piper, espeak-ng), wkhtmltopdf (PDF-Writer-Fallback; Dompdf/TCPDF sind reine PHP), ClamAV (`WHISTLEBLOWING_SCANNER`) | — |

Build-Argumente: `PHP_VERSION` (8.4), `NODE_VERSION` (22), `ALPINE_CSP_BUILD`
(true; muss zum Laufzeit-Flag passen), `WD_WITH_LIBREOFFICE`, `WD_WITH_JAVA`,
`WD_WITH_INOTIFY`, `WD_VERSION` (wird `APP_VERSION`).

```bash
docker build -t workdiary:local --build-arg WD_WITH_JAVA=1 .
docker build -t workdiary-web:local --target web .
```

## 3. Erststart

```bash
git clone <repository-url> workdiary && cd workdiary && git checkout <release-tag>
cp .env.docker.example .env.docker && chmod 600 .env.docker

# 1) Images bauen (app + web); Build-Args siehe Abschnitt 2
docker compose build

# 2) APP_KEY erzeugen und in .env.docker eintragen — NIE wechseln, sichern!
docker run --rm workdiary:local php artisan key:generate --show

# 3) .env.docker pflegen: APP_URL, DB_PASSWORD/MARIADB_PASSWORD (identisch),
#    MARIADB_ROOT_PASSWORD, TRUSTED_PROXIES (Abschnitt 7), Mail

# 4) Erststart mit Migrationen (Opt-in)
WD_AUTO_MIGRATE=1 docker compose up -d
docker compose logs -f app        # „Rolle web: Caches gebaut" abwarten

# 5) Organisation + Plattform-Administrator anlegen (ersetzt den Web-Installer)
docker compose exec app php artisan app:admin --platform \
    --email admin@example.com --name "Admin" --org "Meine Firma"

# 6) Prüfen
docker compose ps                  # app/web/db/queue/scheduler „healthy"/„running"
docker compose exec app php artisan system:health
curl -fsS http://localhost:8080/up
```

Der Web-Installer (`/install`) ist im Container **nicht** nutzbar: Er schreibt
`.env`, die es dort nicht gibt. Deshalb steht `APP_INSTALLED=true` in der
Vorlage, und `app:admin` übernimmt die Admin-Anlage. Weitere Administratoren
oder Passwort-Resets: `app:admin --reset --email …`.

Was der Entrypoint beim Start des `app`-Dienstes tut (und was nicht):

- bricht ohne `APP_KEY` ab; wartet bis zu `WD_DB_WAIT_SECONDS` auf die DB;
- `WD_AUTO_MIGRATE=1`: `migrate --force`, dann **nur** der globale
  `PermissionsSeeder` (Rechte-/Rollenkatalog, idempotent) und `help:reindex`;
- **kein** `db:seed` der org-editierbaren Kataloge (Tätigkeits-/Spesenarten,
  Einsatzarten, …): die legt der `OrganizationObserver` je neuer Organisation
  an; ein Re-Seed würde Anpassungen der Organisationen überschreiben
  (Vollscan J3). Globale Kataloge (Steuerregeln, Meldefristen, DIN 276) bei
  Bedarf einmalig manuell: `docker compose exec app php artisan db:seed --class=TaxRulesSeeder --force`;
- `package:discover`, `config:cache`, `route:cache`, `event:cache` — bewusst
  **kein** `view:cache` (README, Produktions-Checkliste);
- `queue`/`scheduler` warten auf den gesunden `app`-Dienst und bauen ihre
  Caches in ein eigenes, ephemeres `bootstrap/cache`-Volume.

Backup-Heartbeat-Token (Abschnitt 6): `docker compose exec app php artisan
workdiary:backup:rotate-token` gibt den neuen Token aus, kann ihn aber ohne
`.env` nicht speichern → Wert in `.env.docker` eintragen und `docker compose
up -d` (Neustart der App-Dienste).

## 4. Updates

```bash
git fetch --tags && git checkout <neuer-release-tag>
docker compose build                       # neue Images (composer/npm laufen im Build)
WD_AUTO_MIGRATE=1 docker compose up -d     # ersetzt die Container; app migriert
docker compose logs --since 5m app         # Migrationen/Cache-Aufbau prüfen
docker compose exec app php artisan system:health
```

- `docker compose up -d` ersetzt `queue` und `scheduler` mit — ein
  `queue:restart` ist nicht nötig. Laufende Jobs bekommen bis zu 620 s
  (`stop_grace_period`) Zeit.
- Vor jedem Update ein Backup (Abschnitt 6). Rollback = alten Tag auschecken,
  bauen, `up -d` **und** Datenbank aus dem Backup zurückspielen — Migrationen
  laufen nicht rückwärts.
- ENV-Änderungen in `.env.docker` werden erst mit `docker compose up -d`
  wirksam (der Entrypoint baut `config:cache` neu). Ein manuelles
  `docker compose exec app php artisan config:cache` greift dank OPcache-
  Revalidierung (60 s) ebenfalls, wirkt aber nur im `app`-Container.
- Wartungsmodus: `docker compose exec app php artisan down --secret=<token>`
  / `up` — der Marker liegt im geteilten `storage`-Volume und gilt damit für
  alle App-Dienste.

## 5. Volumes

| Volume (`workdiary_*`) | Inhalt                                                    | geteilt von                    |
| ---------------------- | --------------------------------------------------------- | ------------------------------ |
| `storage`              | `storage/app` (Uploads, Anhänge, Exporte), Logs, Sessions/Cache-Dateien | app, queue, scheduler, web (ro) |
| `db-data`              | MariaDB-Datenverzeichnis                                  | db                             |
| `redis-data`           | Redis-AOF (nur Profil `redis`)                            | redis                          |
| anonym                 | `bootstrap/cache` je Container (Caches, bei jedem Start neu) | —                            |

Bind-Mounts statt benannter Volumes sind möglich (`storage:` in einer
`compose.override.yml` auf einen Host-Pfad zeigen lassen); der Pfad muss dann
UID/GID 33 (`www-data`) gehören: `chown -R 33:33 /srv/workdiary/storage`.
`public/storage` ist im Image ein relativer Symlink auf `storage/app/public`;
der `web`-Dienst mountet das Volume schreibgeschützt und liefert öffentliche
Uploads direkt aus.

## 6. Backup und Restore

Zu sichern sind — wie auf Bare-Metal ([backup-restore.md](backup-restore.md) §1):
Datenbank, `storage`-Volume und `.env.docker` (enthält den `APP_KEY`!).

`scripts/backup.sh` ist für Bare-Metal gebaut (liest `.env`, ruft `mysqldump`
auf dem Host). Im Container-Kontext gleichwertig, vom Host per Cron
(Zeitpunkt in der Betriebszeit; `<name>` = Instanzname, wie in §2.1 des
Backup-Handbuchs):

```bash
#!/usr/bin/env bash
set -euo pipefail
cd /srv/workdiary            # Verzeichnis mit compose.yml und .env.docker
STAMP=$(date +%Y%m%d_%H%M%S); OUT=/var/backups/workdiary; mkdir -p "$OUT"; umask 077
# 1) Datenbank (konsistenter Dump inkl. Routinen/Trigger)
docker compose exec -T db sh -c 'exec mariadb-dump --single-transaction --routines --triggers \
    -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' | gzip > "$OUT/<name>_db_$STAMP.sql.gz"
# 2) storage-Volume
docker run --rm -v workdiary_storage:/data:ro -v "$OUT":/backup alpine \
    tar czf "/backup/<name>_storage_$STAMP.tar.gz" -C /data .
# 3) ENV (APP_KEY!)
cp .env.docker "$OUT/<name>_env_$STAMP.txt"
# 4) Manifest + Heartbeat (BACKUP_HEARTBEAT_TOKEN aus .env.docker)
( cd "$OUT" && sha256sum "<name>_"*"_$STAMP"* > "<name>_manifest_$STAMP.sha256" )
TOKEN=$(grep -E '^BACKUP_HEARTBEAT_TOKEN=' .env.docker | cut -d= -f2-)
[ -n "$TOKEN" ] && curl -fsS -X POST -H "Authorization: Bearer $TOKEN" "$(grep -E '^APP_URL=' .env.docker | cut -d= -f2-)/admin/backup/heartbeat" >/dev/null
find "$OUT" -name "<name>_*" -mtime +14 -delete
```

Die **verschlüsselten Cloud-Backupziele** (Feature 017, `BACKUP_MASTER_KEY`,
`workdiary:backup:run/verify/restore-test`) laufen im Container unverändert:
`mysqldump`/`tar` sind im Image, Konfiguration und Schlüssel kommen per ENV,
der Scheduler-Dienst führt die geplanten Läufe aus. Die Backup-Statusseite
(Admin → Backup) meldet fehlende Heartbeats wie gewohnt.

Restore (frischer Host):

```bash
docker compose up -d db                                   # nur die Datenbank
gunzip < <name>_db_….sql.gz | docker compose exec -T db sh -c \
    'exec mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
docker run --rm -v workdiary_storage:/data -v "$PWD":/backup alpine \
    sh -c 'cd /data && tar xzf /backup/<name>_storage_….tar.gz && chown -R 33:33 .'
cp <name>_env_….txt .env.docker && chmod 600 .env.docker    # APP_KEY!
WD_AUTO_MIGRATE=1 docker compose up -d                        # migriert, falls Codebase neuer
docker compose exec app php artisan workdiary:backup:rotate-token   # alter Token steckt im Backup
docker compose exec app php artisan system:health
```

Restore-Tests bleiben Pflicht (Backup-Handbuch §5) — auf einem zweiten Host
oder mit eigenem Compose-Projektnamen (`docker compose -p workdiary-restore …`).

## 7. Reverse-Proxy, TLS und TrustProxies

Der `web`-Dienst veröffentlicht Port `WD_HTTP_PORT` (Default 8080) und
erwartet davor den TLS-Proxy. Der Proxy muss `X-Forwarded-For`,
`X-Forwarded-Proto` und `X-Forwarded-Host` **setzen (überschreiben, nicht
anhängen)**. nginx reicht die Header unverändert an php-fpm durch;
`REMOTE_ADDR` ist dabei die Adresse des `web`-Containers.

Damit Laravel den Headern vertraut, muss `TRUSTED_PROXIES` die Absender-
Adresse enthalten — im Compose-Netz also das Docker-Subnetz:

```bash
docker network inspect workdiary_internal -f '{{range .IPAM.Config}}{{.Subnet}}{{end}}'
# → z. B. 172.18.0.0/16  →  TRUSTED_PROXIES=172.18.0.0/16 in .env.docker
```

`TRUSTED_PROXIES=*` ist nur vertretbar, wenn `web` ausschließlich vom Proxy
erreichbar ist (Port nicht öffentlich). Ohne Eintrag sieht die App für alle
Anfragen die Container-IP: Rate-Limits, Security-Log/fail2ban,
Geo-Erkennung und die Plattform-Admin-IP-Allowlist laufen dann auf einer
Adresse zusammen (siehe `bootstrap/app.php`, Feature 096).

Prüfung nach dem Umbau: Im Security-Log (Admin → Sicherheit) muss der Login
die **Client-IP** zeigen, nicht `172.x`; `APP_URL` mit `https://`, damit
signierte URLs (Portal-Links, Kalender-Feeds) stimmen; `SESSION_SECURE_COOKIE=true`.

Beispiel Caddy (automatisches TLS):

```caddyfile
workdiary.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

Beispiel Traefik-Labels am `web`-Dienst (in einer `compose.override.yml`):

```yaml
services:
  web:
    labels:
      traefik.enable: "true"
      traefik.http.routers.workdiary.rule: Host(`workdiary.example.com`)
      traefik.http.routers.workdiary.entrypoints: websecure
      traefik.http.routers.workdiary.tls.certresolver: letsencrypt
      traefik.http.services.workdiary.loadbalancer.server.port: "80"
```

Reverb (Chat-Echtzeit, optional): `docker compose --profile reverb up -d`,
`BROADCAST_CONNECTION=reverb` + `REVERB_*` in `.env.docker`, den
auskommentierten `/app/`-Block in `deploy/docker/nginx.conf` aktivieren und
den Proxy auf WebSocket-Upgrade stellen (README, Abschnitt Reverse-Proxy).

Redis (optional, empfohlen ab mehreren gleichzeitigen Nutzern):
`docker compose --profile redis up -d` und `CACHE_STORE=redis`,
`QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` in `.env.docker`.

## 8. Ressourcen-Richtwerte

| Dienst    | CPU     | RAM                    | Hinweis                                                              |
| --------- | ------- | ---------------------- | -------------------------------------------------------------------- |
| app       | 1–2     | 1 GB                   | `pm.max_children = 20` × `memory_limit 512M` (Spitze), typisch 60–120 MB je Worker |
| queue     | 1       | 512 MB – 1 GB          | OCR/PDF-Jobs (ocrmypdf, Ghostscript) sind die Spitzen; bei viel Beleg-Import zweiten Worker: `docker compose up -d --scale queue=2` |
| scheduler | < 0,5   | 256 MB                 | startet Jobs, rechnet selbst wenig                                   |
| db        | 1       | 1–2 GB                 | `WD_DB_BUFFER_POOL` (Default 512M) ≈ 50–70 % des DB-RAM              |
| web       | < 0,5   | 64 MB                  | statisch + FastCGI-Proxy                                             |

Kleinstinstallation (bis ~10 Nutzer): 2 vCPU / 4 GB gesamt. 50 Nutzer mit
Beleg-OCR: 4 vCPU / 8 GB und Redis-Profil.

Limits lassen sich per `deploy.resources.limits` in einer
`compose.override.yml` setzen; der Docker-Logtreiber sollte rotieren
(`--log-opt max-size=50m`), da alle Dienste nach stderr loggen.

## 9. Betrieb und Diagnose

```bash
docker compose logs -f app queue scheduler        # LOG_CHANNEL=stderr
docker compose exec app php artisan schedule:list # Zeiten in der Betriebszeit?
docker compose exec app php artisan queue:monitor database:default --max=100
docker compose exec app php artisan workdiary:backup:status
docker compose exec app php artisan integrity:verify
docker compose exec app php artisan system:health --json
```

Health: `GET /up` (Laravel-Health-Route) — der Container-Healthcheck ruft sie
alle 30 s per FastCGI; `docker compose ps` zeigt `healthy`. Der Scheduler
läuft als `schedule:work` dauerhaft — er holt verpasste Läufe **nicht** nach
(Host-Abschaltzeiten wie bei Bare-Metal beachten, [systemdienste.md](systemdienste.md)).

## 10. Bekannte Grenzen

- **Kein Multi-Node-Betrieb.** `storage` ist ein lokales Volume, der Scheduler
  läuft genau einmal; horizontale Skalierung über mehrere Hosts (geteiltes
  Storage, Lock-Koordination) ist nicht vorgesehen und nicht getestet.
  Skalierbar ist nur `queue` auf demselben Host.
- **SQLite nur für Demo/Test**: Web, Queue und Scheduler schreiben parallel —
  produktiv MariaDB/MySQL (compose) oder PostgreSQL (extern, `pdo_pgsql` ist
  im Image).
- Kein Web-Installer im Container (ENV statt `.env`), Admin-Anlage über `app:admin`.
- Keine veröffentlichten Images: selbst bauen (Abschnitt 2/3); der CI-Job
  `docker-build` ist nicht blockierend und pusht nicht.
- `integrity:watch` braucht `WD_WITH_INOTIFY=1` und einen eigenen Dienst
  (Kommando `php artisan integrity:watch`); der tägliche `integrity:verify`
  läuft ohne Zusatz. Die Git-Prüfung des Integritätsdienstes entfällt (kein
  `.git` im Image).
- LibreOffice-, Java- und Medien-Funktionen nur mit den genannten Build-Args
  bzw. gar nicht (Abschnitt 2); `WHISTLEBLOWING_SCANNER=clamav` braucht einen
  externen `clamd`.
- `.env.docker` enthält auch `MARIADB_ROOT_PASSWORD`; die Datei ist `chmod 600`
  zu halten und liegt als ENV in allen App-Containern. Wer das trennen will,
  gibt dem `db`-Dienst in einer `compose.override.yml` eine eigene `env_file`.

## Verweise

- [systemdienste.md](systemdienste.md) — Bare-Metal-Systemdienste (Cron, Queue, Reverb, fail2ban)
- [backup-restore.md](backup-restore.md) — Backup-Strategie, Cloud-Ziele, Restore-Tests
- [geoip.md](geoip.md) — GeoIP-Datenbank (`GEOIP_DATABASE` auf einen Pfad im `storage`-Volume legen)
- Konzept: WorkDiary-Architecture, `features/015-mandantenfaehigkeit-betriebsmodelle.md`
