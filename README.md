# WorkDiary Next

WorkDiary Next ist die moderne Laravel-Neuentwicklung des bisherigen
Online-Tagebuchs. Die Anwendung ist inzwischen nicht mehr nur eine
Legacy-Bruecke, sondern ein mandantenfaehiges Arbeits-, Zeit-, Einsatz- und
Nachweissystem fuer interne Teams, Kundenportale und lokale/Private-Cloud-
Installationen.

## Aktueller Stand

- Laravel 13, PHP 8.3, Vite 8, Tailwind CSS 4 und DaisyUI 5.
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

Die Produkt-Roadmap liegt unter [`docs/features`](docs/features/README.md).

## Voraussetzungen

- PHP 8.3 oder neuer
- Composer
- Node.js mit npm
- SQLite fuer lokale Entwicklung oder eine externe Datenbank
- `fileinfo` fuer sichere Upload-Pruefungen

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
  einrichten.
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
[`docs/features`](docs/features/README.md) gepflegt. Dort sind Datenschutz,
Mandantenfaehigkeit, Zeiterfassung, Auswertungen, Dokumentation,
Kundenportal, Integrationen, Backup/Restore, Hilfe, UX und weitere
Produktbereiche priorisiert.
