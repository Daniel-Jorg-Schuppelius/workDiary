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
