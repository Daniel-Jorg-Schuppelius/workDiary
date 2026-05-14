# WorkDiary Next

Neues Laravel-Projekt fuer das bisherige Online-Tagebuch. Ziel ist eine moderne, wartbare Anwendung mit zeitgemaesser Oberflaeche und einer sauberen Bruecke zu den vorhandenen Legacy-Tabellen.

## Status

- Laravel 13 mit Vite und Tailwind CSS 4 ist eingerichtet.
- Die Startseite zeigt bereits Live-Daten aus der alten Struktur an, sobald eine Legacy-DB konfiguriert ist.
- Die Tabellen `user` und `tagebuch` werden zunaechst nur gelesen. Das Altsystem bleibt damit kompatibel.
- Fuer eine spaetere Portierung ist ein erstes Analyse-Kommando vorbereitet.
- Der alte Dump `_B_A_C_K_U_P_db473282568.sql.gz` bestaetigt aktuell `latin1` und `latin1_german2_ci` fuer die Legacy-Daten.

## Lokale Entwicklung

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan serve
npm run dev
```

## Legacy-Datenbank anbinden

In der `.env` werden die bestehenden Zugangsdaten nicht ueber `DB_*`, sondern ueber eigene Legacy-Variablen gesetzt:

```dotenv
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=
LEGACY_DB_USERNAME=
LEGACY_DB_PASSWORD=
LEGACY_DB_SOCKET=
LEGACY_DB_CHARSET=latin1
LEGACY_DB_COLLATION=latin1_german2_ci
LEGACY_DB_PREFIX=
```

Sobald diese Werte gesetzt sind, liest die neue App direkt aus den vorhandenen Tabellen `user` und `tagebuch`.

Der vorhandene Dump zeigt fuer die Kernobjekte aktuell diese Legacy-Struktur:

- `user`: ca. 16 Datensaetze, Felder `id`, `uname`, `userpw`, `email`
- `tagebuch`: ca. 5711 Datensaetze, Felder `id`, `aktuell`, `user`, `von`, `bis`, `inhalt`, `antwort`, `gelesen`, `sms`

## Wichtige Befehle

```bash
php artisan test
npm run build
php artisan legacy:import-plan
```

`legacy:import-plan` zeigt den Umfang der Alt-Daten an und ist die Grundlage fuer ein spaeteres echtes Importskript.

## Architektur

- `app/Models/Legacy/LegacyUser.php`: Legacy-Modell fuer die bestehende Tabelle `user`
- `app/Models/Legacy/LegacyDiaryEntry.php`: Legacy-Modell fuer die bestehende Tabelle `tagebuch`
- `app/Http/Controllers/HomeController.php`: Dashboard-Startseite mit Live-Kennzahlen
- `resources/views/home.blade.php`: neue Oberflaeche fuer die Modernisierung

## Naechste Ausbaustufen

1. Anmeldung gegen Legacy-User oder kontrollierte Benutzer-Migration mit Passwort-Hashing.
2. Filterbares Aufgaben-Dashboard und neue Eintragsmasken.
3. Dediziertes Importskript fuer die Ablösung vom Altsystem.

## Produktions-Checkliste (Sicherheit)

Vor dem Produktiv-Deployment unbedingt prüfen:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…` setzen.
- `php artisan key:generate` ausgeführt; `APP_KEY` rotieren bei Verdacht.
- `php artisan config:cache route:cache` für Performance. **`view:cache` NICHT** verwenden – sammelt alle Views in einer Compiler-Instanz, was den internen `forElseCounter` driften lässt und u. a. Views mit `@forelse` korrupt mit `$__empty_-N` kompiliert. Views lazy pro Request kompilieren lassen.
- HTTPS erzwingen (Reverse-Proxy + `TrustProxies`); `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_ENCRYPT=true` (siehe `.env.example`).
- Rate-Limiter aktiv: `throttle:login` (5/min email+ip + 20/min ip), `throttle:register` (3/min ip), `throttle:password` (5/min + 20/h user+ip) — definiert in `App\Providers\AppServiceProvider`.
- `Password::defaults()`: min 12 Zeichen, MixedCase, Letters, Numbers, Symbols + `uncompromised()` (HIBP-Pwned-Check) in Production.
- `App\Http\Middleware\SecurityHeaders` setzt CSP, COOP, CORP, HSTS (in Production), X-Frame-Options, Referrer-Policy.
- Audit-Log: `App\Listeners\AuthEventSubscriber` protokolliert Login/Logout/Failed/Lockout/PasswordReset.
- Anhänge: 25 MB Limit, Whitelist-Erweiterungen, Magic-Bytes-Prüfung via `fileinfo` (`AttachmentController`).
- Backups: tägliche DB-Sicherung + Storage (`storage/app/attachments/`) einrichten.
- Optional / TODO: 2FA für Admin-Konten (`pragmarx/google2fa-laravel`).
- Optional / TODO: `Gate::after()` Logging für Policy-Denials.
