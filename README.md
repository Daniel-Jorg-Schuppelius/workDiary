# WorkDiary Next

WorkDiary Next ist die moderne Laravel-Neuentwicklung des bisherigen
Online-Tagebuchs. Die Anwendung ist inzwischen nicht mehr nur eine
Legacy-Bruecke, sondern ein mandantenfaehiges Arbeits-, Zeit-, Einsatz- und
Nachweissystem fuer interne Teams, Kundenportale und lokale/Private-Cloud-
Installationen.

## Aktueller Stand

- Laravel 13, PHP 8.4, Vite 8, Tailwind CSS 4 und DaisyUI 5.
- Mandanten, Organisationen, Rollen, Gruppen und Berechtigungen auf Basis von
  `spatie/laravel-permission`.
- Tagebuch, Kommentare, Anhaenge, Tags, Archiv, Wochenansicht, Kalender,
  Kanban und globale Suche.
- Kunden, Projekte, Aufgaben, Meilensteine, Stundenzettel, Material,
  Stoppuhr, Anwesenheit, Gleitzeit, Monatsfreigaben, Zeitkorrekturen und
  Zeitexporte.
- Dienstplanung mit Dienstplaenen, Soll-Besetzung, Schichttypen,
  geplanten Schichten, Urlaubs-/Krankheitsverwaltung, Notdienst und
  Drucklayouts.
- Kundenportal mit getrenntem Guard fuer Tagebuch, Zeiten, Rechnungen und
  offene Punkte.
- Assets, Fahrzeuge, Energie-Logs, Software, Wartungsplaene, Service-Tickets,
  SLA-Logik, Schluesseluebergaben und Zaehlerstaende.
- Protokolle, offene Punkte, Checklisten/Procedures, Abnahmen, Magic-Link-
  Signaturen und PDF-Ausgabe.
- Rechnungen, Vorlagen, Mail-Templates, Spesen, Verpflegungsmehraufwand,
  Reise-/Fahrtenbuch und Lexoffice-Anbindung.
- Auswertungen fuer Zeiten, Kunden, Projekte, Assets, Fuhrpark, Abrechnung,
  Spesen, Qualifikationen, Abwesenheiten, Anwesenheit, Audit und Plan/Ist.
- Plugin-System mit Admin-UI, Health-Checks und Fehler-Inbox. Aktuelle Plugins:
  Lexoffice, Toggl und RemoteSupport.
- Legacy-Modul fuer vorhandene Tabellen, Callcenter-Ansicht, Archiv,
  Migration und optional schreibende Legacy-Routen.
- Sicherheits- und Betriebsfunktionen: CSP/Security-Headers, Rate-Limits,
  Audit-Log, Backup-Heartbeat, Diagnose-Seite, Datenschutz-Export,
  Lizenz-/Feature-Gates, Demo-Mandanten und In-App-Hilfe.

Die Produkt-Roadmap liegt im Doku-Repository [WorkDiary-Architecture](https://github.com/Daniel-Jorg-Schuppelius/WorkDiary-Architecture/blob/main/features/README.md).

## Lizenz und kommerzielle Leistungen

WorkDiary wird unter der
[GNU Affero General Public License v3.0 oder spaeter](LICENSE) veroeffentlicht.
Die AGPL-Version darf auch kommerziell genutzt, veraendert und selbst betrieben
werden. Bei einer veraenderten, ueber ein Netzwerk bereitgestellten Version
muessen deren Benutzer den zugehoerigen Quellcode gemaess AGPL erhalten.

Die oeffentliche Community-Version wird ohne garantierte Reaktionszeiten,
Wartungsfristen oder individuelle Betriebsunterstuetzung bereitgestellt.
Kostenpflichtig angeboten werden koennen insbesondere:

- priorisierte Fehlerbehebung, Sicherheitsupdates und langfristige Pflege,
- LTS-Releases, getestete Update-Pfade und Migrationsunterstuetzung,
- Installation, Hosting, Monitoring, Backups und Wiederherstellung,
- Support mit vereinbarten Reaktionszeiten und Service Level Agreements,
- Schulungen, Integrationen und kundenspezifische Entwicklung,
- eine gesonderte kommerzielle Lizenz fuer Anforderungen, die nicht mit der
  AGPL vereinbar sind.

Einzelheiten stehen in
[`COMMERCIAL-SERVICES.md`](COMMERCIAL-SERVICES.md). Technische Lizenzschluessel
weisen den Anspruch auf gebuchte Releases, Funktionen oder Dienstleistungen
nach; sie beschraenken nicht die Rechte, die Empfaenger unter der AGPL an einer
AGPL-Ausgabe erhalten.

## Voraussetzungen

- PHP 8.4 oder neuer
- Composer
- Node.js mit npm
- SQLite fuer lokale Entwicklung oder eine externe Datenbank
- PHP-Erweiterungen: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`,
  `mbstring`, `openssl`, `pdo`, `tokenizer`, `xml` und passend zur Datenbank
  z. B. `pdo_mysql` oder `pdo_sqlite`

Optionale Integrationen benoetigen eigene Zugangsdaten, z. B. Legacy-DB,
Lexoffice, Push/VAPID, Mail, Remote-Support-Provider oder Toggl.

## Lokale Entwicklung

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Danach Anwendung und Frontend starten:

```bash
php artisan serve
npm run dev
```

Alternativ startet das Composer-Skript Server, Queue, Logs und Vite zusammen:

```bash
composer dev
```

Das Setup-Skript installiert Abhaengigkeiten, erzeugt die `.env`, fuehrt
Migrationen aus und baut die Assets:

```bash
composer setup
```

## Gefuehrte Installation (Web-Installer)

Fuer eine frische Installation steht ein gefuehrter Assistent bereit. Es genuegt
das Repository auszuchecken und Abhaengigkeiten zu installieren – `APP_KEY`,
Datenbank, Migrationen sowie der erste Mandant und Admin-Benutzer werden vom
Installer erzeugt.

> **Wichtig:** Es wird PHP 8.4 oder neuer benoetigt. Mit aelteren Versionen
> (z. B. PHP 8.2/8.3) bricht `composer install` ab, weil Abhaengigkeiten wie
> Symfony 8 und `simshaun/recurr` mindestens PHP 8.4 verlangen. Bei vielen
> Hostern laesst sich die PHP-Version im Hosting-Panel oder per `.htaccess`
> umstellen.

```bash
git clone <repository-url> workdiary
cd workdiary
composer install
npm ci && npm run build
```

Anschliessend die Domain auf `public/` zeigen lassen und im Browser `/install`
oeffnen. Der Assistent fuehrt durch folgende Schritte:

1. Systemvoraussetzungen (PHP-Version, Erweiterungen, Schreibrechte).
2. Anwendung (Name, URL, Umgebung) inkl. automatischer `APP_KEY`-Erzeugung.
3. Datenbank (SQLite, MySQL oder PostgreSQL) inkl. Verbindungstest und
   Migrationen.
4. Erster Mandant und Administrator-Konto.
5. Mail/SMTP.
6. Optionale Integrationen (Lexoffice, Web-Push/VAPID).

Nach Abschluss wird die Sperrdatei `storage/installed` angelegt; der Installer
ist danach automatisch deaktiviert und `/install` liefert `404`. Solange diese
Datei fehlt, leitet die Anwendung alle Aufrufe auf den Assistenten um. Ein
vorhandener `APP_KEY` wird niemals ueberschrieben.

Alternativ laeuft derselbe Ablauf interaktiv auf der Kommandozeile:

```bash
php artisan app:install
```

## Installation auf einem Webspace

Der Dokumentenstamm der Domain muss auf das Verzeichnis `public/` zeigen. Wenn
der Hoster keinen direkten Webroot auf `public/` erlaubt, sollte das Projekt
nicht in ein oeffentlich erreichbares Verzeichnis gelegt werden; alternativ muss
der Hoster eine passende Weiterleitung oder ein Unterverzeichnis als Webroot
konfigurieren koennen.

Empfohlener Ablauf per SSH:

```bash
git clone <repository-url> workdiary
cd workdiary
cp .env.example .env
```

Danach in `.env` mindestens diese Werte fuer den Webspace setzen:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

Anschliessend die Installationsroutine starten:

```bash
bash scripts/install-webspace.sh --url=https://example.com
```

Die Routine fuehrt die typischen Deployment-Schritte aus:

- Production-Defaults in `.env` setzen.
- Composer-Abhaengigkeiten ohne Dev-Pakete installieren.
- `APP_KEY` erzeugen, falls noch keiner vorhanden ist.
- Frontend-Assets mit npm bauen.
- Schreibrechte fuer `storage/` und `bootstrap/cache/` vorbereiten.
- `storage:link`, Datenbank-Migrationen und Laravel-Caches ausfuehren.
- Queue-Worker per `queue:restart` neu anstossen.

Wenn der Webspace kein Node.js bereitstellt, die Assets lokal oder in CI bauen
und mit hochladen:

```bash
npm ci
npm run build
bash scripts/install-webspace.sh --url=https://example.com --skip-assets
```

Wenn Composer auf dem Webspace ebenfalls nicht verfuegbar ist, `vendor/` lokal
mit PHP 8.4-kompatibler Umgebung bauen und hochladen:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

Auf dem Webspace danach nur noch die Server-Schritte ausfuehren:

```bash
bash scripts/install-webspace.sh --url=https://example.com --skip-composer --skip-assets
```

Fuer den laufenden Betrieb muss ein Cronjob fuer den Scheduler eingerichtet
werden:

```cron
* * * * * cd /pfad/zum/workdiary && php artisan schedule:run >> /dev/null 2>&1
```

Queues laufen produktiv am besten als dauerhafter Prozess:

```bash
php artisan queue:work --tries=3
```

Wenn der Hoster keinen dauerhaften Worker erlaubt, kann fuer kleine
Installationen alternativ ein regelmaessiger Cronjob genutzt werden:

```cron
* * * * * cd /pfad/zum/workdiary && php artisan queue:work --stop-when-empty --tries=3 >> /dev/null 2>&1
```

### Chat-Echtzeit (Reverb) — optional

Der Chat aktualisiert sich **immer ohne Reload**, auch ganz ohne Zusatzdienste:
ein eingebautes Polling holt alle ~3 s neue Nachrichten. Fuer **sofortige**
(sub-sekuendliche) Zustellung gibt es zusaetzlich WebSockets via Laravel Reverb.

> **Deploy-Hinweis:** Das Chat-Frontend bindet die npm-Pakete `laravel-echo`
> und `pusher-js` ein (in `package.json`/`package-lock.json` enthalten). Nach
> einem Update daher **vor** `npm run build` zwingend `npm ci` (bzw.
> `npm install`) ausfuehren — sonst schlaegt der Build mit
> „Rolldown failed to resolve import 'laravel-echo'" fehl.

Die Reverb-Zugangsdaten werden lokal erzeugt; es ist kein externer Dienst
erforderlich:

```bash
openssl rand -hex 16  # REVERB_APP_KEY
openssl rand -hex 32  # REVERB_APP_SECRET
```

Die Ausgaben zusammen mit einer frei waehlbaren `REVERB_APP_ID` in `.env`
eintragen. Hinter einem HTTPS-Reverse-Proxy zeigt `REVERB_*` auf den intern
erreichbaren Reverb-Prozess, waehrend `VITE_REVERB_*` die oeffentlich
erreichbare Domain beschreibt:

```dotenv
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

REVERB_APP_ID=workdiary
REVERB_APP_KEY=hier_den_ersten_zufallswert_eintragen
REVERB_APP_SECRET=hier_den_zweiten_zufallswert_eintragen

REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=workdiary.example.org
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`REVERB_APP_SECRET` darf nicht an den Browser oder in das Repository gelangen.
Der `REVERB_APP_KEY` ist dagegen eine oeffentliche Anwendungskennung und wird
beim Frontend-Build eingebettet. Nach Aenderungen:

```bash
php artisan optimize:clear
npm run build
```

Fuer echte Echtzeit muessen zwei dauerhafte Prozesse laufen — der WebSocket-Server
und ein Queue-Worker (die Broadcast-Events laufen ueber die Queue). Lokal sind
beide bereits in `composer dev` enthalten.

#### Betrieb mit systemd

Auf Systemen mit systemd werden zwei Dienste angelegt. In beiden Dateien muessen
`/pfad/zum/workdiary` und gegebenenfalls `/usr/bin/php` angepasst werden
(`command -v php` zeigt den PHP-Pfad).

`/etc/systemd/system/workdiary-reverb.service`:

```ini
[Unit]
Description=WorkDiary Laravel Reverb
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/pfad/zum/workdiary
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=always
RestartSec=5
TimeoutStopSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/workdiary-queue.service`:

```ini
[Unit]
Description=WorkDiary Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/pfad/zum/workdiary
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=90 --max-time=3600
Restart=always
RestartSec=5
TimeoutStopSec=100
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

Dienste laden, beim Systemstart aktivieren und sofort starten:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now workdiary-reverb workdiary-queue
sudo systemctl status workdiary-reverb workdiary-queue
```

Logs lassen sich ueber das Journal verfolgen:

```bash
sudo journalctl -u workdiary-reverb -f
sudo journalctl -u workdiary-queue -f
```

Nach einem Deployment beide Prozesse neu starten:

```bash
sudo systemctl restart workdiary-reverb workdiary-queue
```

Der konfigurierte Benutzer muss `.env` lesen und in `storage` sowie
`bootstrap/cache` schreiben koennen. Bei Hosting-Setups sollte statt `www-data`
gegebenenfalls der Benutzer des jeweiligen Webauftritts verwendet werden.

Ein Fehler wie `Pusher\Pusher::__construct(): ... auth_key ... null given`
bedeutet, dass `REVERB_APP_KEY` in `.env` fehlt oder noch eine alte Laravel-
Konfiguration gecacht ist. Nach dem Eintragen hilft `php artisan optimize:clear`.

#### Betrieb mit Supervisor

Alternativ koennen die Prozesse per Supervisor verwaltet werden:

```ini
[program:workdiary-reverb]
command=php /pfad/zum/workdiary/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
stopwaitsecs=10

[program:workdiary-queue]
command=php /pfad/zum/workdiary/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
stopwaitsecs=10
```

#### Reverse-Proxy

Hinter einem Webserver wird `/app/...` als WebSocket an Reverb durchgereicht.
Fuer Apache im HTTPS-`VirtualHost`:

```apache
ProxyPreserveHost On

ProxyPass        "/app/" "ws://127.0.0.1:8080/app/" retry=0 timeout=600
ProxyPassReverse "/app/" "ws://127.0.0.1:8080/app/"
```

Die erforderlichen Apache-Module aktivieren und die Konfiguration pruefen:

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel
sudo apachectl configtest
sudo systemctl restart apache2
```

Alternativ fuer nginx:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
}
```

Ohne Supervisor laesst sich Reverb per **Cron-Watchdog** am Leben halten (minuetlich
neu starten, falls abgestuerzt):

```cron
* * * * * pgrep -f "artisan reverb:start" >/dev/null || (cd /pfad/zum/workdiary && nohup php artisan reverb:start --port=8080 >> storage/logs/reverb.log 2>&1 &)
```

Wer auf Reverb verzichtet, bleibt beim Polling — der Chat funktioniert
vollstaendig, nur eben mit ~3 s statt sofort. Details: [chat.md](https://github.com/Daniel-Jorg-Schuppelius/WorkDiary-Architecture/blob/main/chat.md).

## Wichtige Konfiguration

Die lokale `.env.example` nutzt standardmaessig SQLite:

```dotenv
DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
MAIL_MAILER=log
```

Fuer Web-Push:

```dotenv
VAPID_SUBJECT=mailto:admin@example.com
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
```

Fuer Lexoffice:

```dotenv
LEXOFFICE_API_KEY=
```

Legacy-Schreibzugriffe sind bewusst deaktiviert und muessen explizit gesetzt
werden:

```dotenv
LEGACY_WRITE_ENABLED=false
```

## Legacy-Datenbank

Die Legacy-Datenbank wird ueber eigene Variablen angebunden, nicht ueber die
primaeren `DB_*`-Werte:

```dotenv
LEGACY_DB_DRIVER=mysql
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
LEGACY_DB_SOCKET=
LEGACY_DB_CHARSET=latin1
LEGACY_DB_COLLATION=latin1_german2_ci
LEGACY_DB_PREFIX=
LEGACY_MYSQL_ATTR_SSL_CA=
LEGACY_DB_ENCRYPT=no
LEGACY_DB_TRUST_SERVER_CERTIFICATE=true
```

Das Legacy-Modul liest die vorhandenen Strukturen fuer Benutzer, Tagebuch,
Notdienst, Bereitschaft und Archiv. Schreibende Legacy-Routen sind zusaetzlich
durch `LEGACY_WRITE_ENABLED=true` geschuetzt.

Hilfreiche Legacy-Befehle:

```bash
php artisan legacy:import-plan
php artisan legacy:import
php artisan legacy:archive
```

## Qualitaetssicherung

```bash
php artisan test
composer test
composer lint
composer format
composer qa
npm run build
```

Weitere Hilfsbefehle:

```bash
php artisan plugin:list
php artisan plugin:healthcheck
php artisan help:reindex
php artisan recurrence:generate
php artisan attendance:close-open
php artisan workdiary:backup:check-restore
```

## Architektur

- `routes/web.php`: interne Web-Anwendung mit Auth, Admin, Planung,
  Abrechnung, Reports und Self-Service.
- `routes/api.php`: Sanctum-API fuer Tagebuch, Zeiten, Projekte,
  Stundenzettel, Assets und Push.
- `routes/customer.php`: technisch getrenntes Kundenportal mit eigenem Guard.
- `routes/legacy.php`: isoliertes Legacy-Modul mit separaten Middlewares.
- `app/Models`: Eloquent-Modelle des neuen Systems.
- `app/Legacy`: Legacy-Models, Controller, Services und Konsolenbefehle.
- `app/Services`: Fachlogik fuer Zeit, Planung, Abrechnung, Assets,
  Protokolle, Support, Lizenzierung und weitere Domaenen.
- `app/Plugins`: Plugin-Vertraege, Manager und konkrete Plugins.
- `resources/views`: Blade-Oberflaeche und Druck-/PDF-Views.
- `database/migrations`: aktuelle Datenstruktur fuer Mandanten, Rechte,
  Zeiten, Planung, Rechnungen, Assets, Reports, Plugins und Legacy-Migration.

## Produktions-Checkliste

Vor dem Produktiv-Deployment:

- `APP_ENV=production`, `APP_DEBUG=false` und korrekte `APP_URL` setzen.
- `APP_KEY` erzeugen und bei Verdacht rotieren.
- `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`,
  `SESSION_SAME_SITE=lax` und `SESSION_ENCRYPT=true` setzen.
- HTTPS ueber Reverse Proxy erzwingen und `TrustProxies` passend
  konfigurieren.
- `php artisan config:cache` und `php artisan route:cache` ausfuehren.
- `php artisan view:cache` nicht verwenden. Das Projekt laesst Views bewusst
  lazy pro Request kompilieren, weil gecachte Sammelkompilierung bei
  `@forelse`-Views zu fehlerhaften internen Counter-Namen fuehren kann.
- Queue-Worker, Scheduler, Backup, Restore-Test und Storage-Sicherung
  einrichten (Anleitung: [`docs/backup-restore.md`](docs/backup-restore.md)).
- `storage/app/attachments/` in die Sicherung aufnehmen.
- Mail, Push, Lizenz, Plugins und externe Integrationen separat pruefen.

## Sicherheit

- Login, Registrierung, Passwortwechsel, Signatur-Links, Diagnose und
  Supportberichte sind rate-limitiert.
- Passwoerter erzwingen in Production starke Regeln und
  `uncompromised()`-Pruefung.
- `App\Http\Middleware\SecurityHeaders` setzt CSP, COOP, CORP, HSTS,
  X-Frame-Options und Referrer-Policy.
- `App\Listeners\AuthEventSubscriber` protokolliert Auth-Ereignisse.
- Uploads sind auf 25 MB begrenzt und werden per Erweiterungs-Whitelist sowie
  Magic-Bytes-Pruefung validiert.
- Mandantenkontext wird im Web- und API-Stack vor Route-Model-Binding gesetzt,
  damit Scopes auch bei gebundenen Modellen greifen.

## Roadmap

Die fachliche Roadmap und MVP-Zerlegung werden in
[WorkDiary-Architecture/features](https://github.com/Daniel-Jorg-Schuppelius/WorkDiary-Architecture/blob/main/features/README.md) gepflegt. Dort sind Datenschutz,
Mandantenfaehigkeit, Zeiterfassung, Auswertungen, Dokumentation,
Kundenportal, Integrationen, Backup/Restore, Hilfe, UX und weitere
Produktbereiche priorisiert.
