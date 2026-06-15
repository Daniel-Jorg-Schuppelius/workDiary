# Release-Prozess

Status: Aktiv (Feature 022, MVP) • Quelle:
[Feature 022 — Release-, Update- und Plugin-Strategie](features/022-release-update-plugin-strategie.md).

## 1. Versionierung (SemVer)

WorkDiary versioniert nach [Semantic Versioning](https://semver.org/lang/de/):

- **MAJOR** — inkompatible Änderungen (Datenmodell-Brüche, entfernte APIs).
- **MINOR** — neue Funktionalität, abwärtskompatibel.
- **PATCH** — Fehlerbehebungen, abwärtskompatibel.

Die laufende Version steht in `config('app.version')` und wird pro
Installation über die Umgebungsvariable `APP_VERSION` gesetzt (Beispiel:
`APP_VERSION=1.2.0`). Ohne Eintrag greift der Dev-Fallback `0.1.0-dev`.
Angezeigt wird die Version im Footer, auf der Betriebsmetrik-Seite
(`admin/metrics`), auf der Diagnose-Seite und in `system:health`.

## 2. CHANGELOG-Pflege

`CHANGELOG.md` im Repo-Root folgt dem
[Keep-a-Changelog](https://keepachangelog.com/de/1.1.0/)-Format:

1. Jede Änderung landet während der Entwicklung unter `[Unreleased]`
   (Kategorien: `Added`, `Changed`, `Fixed`, `Removed`, `Security`).
2. Beim Release wird `[Unreleased]` in einen Versions-Abschnitt mit Datum
   umbenannt (`## [1.2.0] - 2026-06-10`) und ein neuer leerer
   `[Unreleased]`-Abschnitt angelegt.
3. Migrations- oder Konfigurationshinweise (neue ENV-Variablen, manuelle
   Schritte) gehören explizit in den Versions-Abschnitt.

## 3. Update-Schritte (lokale Installation)

Vor dem Update zwingend ein Backup ziehen — siehe
[Backup & Restore](backup-restore.md).

```bash
# 1. Wartungsmodus
php artisan down

# 2. Neuen Stand einspielen (git pull / Release-Archiv entpacken)

# 3. Abhängigkeiten und Assets
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 4. Datenbank migrieren
php artisan migrate --force

# 5. Caches erneuern
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Health-Check NACH dem Update
php artisan system:health

# 7. Wartungsmodus beenden
php artisan up
```

`system:health` prüft Datenbank, ausstehende Migrationen, Storage-
Beschreibbarkeit, Queue-, Mail- und APP_KEY-Konfiguration sowie den
Lizenzstatus und endet mit Exit-Code 0 (gesund) bzw. 1 (Problem) —
geeignet für Update-Skripte und Monitoring.

## 3a. SBOM je Release (Feature 044)

Für jede ausgelieferte Version wird eine maschinenlesbare Software-
Stückliste (SBOM, CycloneDX 1.5 JSON) erzeugt und dem Release zugeordnet:

```bash
php artisan sbom:generate
```

- Quellen: `composer.lock`, `package-lock.json`, Laufzeitversionen
  (PHP/Laravel/DB-Treiber), WorkDiary-Module und registrierte Plugins.
- Ablage: `storage/app/sbom/workdiary-{version}-{Zeitstempel}.cdx.json`
  plus stabiler Alias `workdiary-latest.cdx.json`; der Command gibt den
  **SHA-256** der Datei aus — diesen Hash in die Release-Notes übernehmen.
- Optionen: `--output=<pfad>` (abweichender Zielpfad, z. B. CI-Artefakt)
  und `--print` (Ausgabe nach stdout statt Datei).
- Einsicht/Erzeugung/Download auch über die geschützte Admin-Seite
  **System → Komponenten & Versionen** (`admin/components`, nur Admin).
- Die SBOM ist eine Komponenteninventur — **kein** Schwachstellenbericht
  (Orientierung: BSI TR-03183-2); der Advisory-Abgleich folgt separat.

## 3b. Signiertes Release-Manifest (Feature 022)

Zusätzlich zur SBOM wird je Release ein **integritätsgesichertes
Release-Manifest** erzeugt, das eine konkrete Installation einem Release
zuordnet:

```bash
php artisan release:manifest
```

- Inhalt: App-/Build-Version (`config('app.version')` + Git-Kurz-Hash),
  PHP-/Laravel-/DB-Versionen, Erstellungszeit, aktive Module und registrierte
  Plugins (mit Version und Kompatibilitätsbereich) sowie **SHA-256-Prüfsummen**
  der release-relevanten Artefakte (SBOM-Alias `workdiary-latest.cdx.json`,
  `composer.lock`, `package-lock.json`).
- Ablage: `storage/app/release/release.json`. Optionen: `--output=<pfad>`
  (z. B. CI-Artefakt) und `--print` (stdout).
- **Signatur**: Ist ein **Ed25519-Private-Key** konfiguriert
  (`LICENSE_PRIVATE_KEY` bzw. `LICENSE_PRIVATE_KEY_FILE` — derselbe Schlüssel
  wie das Lizenzsystem), wird das Manifest signiert. Auf Kundeninstallationen
  ohne Private Key bleibt das Manifest **unsigniert**, die Prüfsummen-
  Integrität ist trotzdem nachweisbar.
- **Verifikation** (auch im Update-/Monitoring-Skript):

  ```bash
  php artisan release:verify           # Default: storage/app/release/release.json
  php artisan release:verify /pfad/zu/release.json
  ```

  Prüft jede Artefakt-Prüfsumme gegen die aktuelle Datei und — falls signiert —
  die Signatur gegen den (versiegelten/konfigurierten) Public Key. Exit-Code 0
  = gültig, 1 = manipuliert/abweichend.
- Erzeugung/Download/Status auch über **System → Komponenten & Versionen**
  (`admin/components`), dort zusammen mit dem `system:health`-Status für den
  „nach Update"-Ablauf.

## 4. Rollback

- **Vor jedem Update**: vollständiges Backup (Datenbank + `storage/`),
  siehe [Backup & Restore](backup-restore.md).
- Schlägt das Update fehl (Migration bricht ab, `system:health` rot):
  Wartungsmodus aktiv lassen, vorherigen Code-Stand wiederherstellen und
  das Backup einspielen. Migrationen werden NICHT einzeln zurückgerollt
  (`migrate:rollback` ist für Produktivdaten nicht vorgesehen) — die
  Wiederherstellungsbasis ist immer das Backup.

## 5. Offene Punkte (außerhalb des MVP)

- ~~Maschinenlesbare SBOM (Composer-/NPM-/Plugin-Versionen) je Release.~~
  Umgesetzt: `php artisan sbom:generate` (siehe §3a).
- ~~Signierte Release-Metadaten (Build-Hash, Prüfsummen).~~ Umgesetzt:
  `php artisan release:manifest` / `release:verify` (Ed25519-Signatur, sonst
  SHA-256-Integrität; siehe §3b).
- ~~Plugin-Kompatibilitätsangaben + Durchsetzung je Kern-Version.~~ Umgesetzt:
  `Plugin::minAppVersion()` / `maxAppVersion()` + `PluginCompatibility`
  (Aktivierung/Healthcheck blockieren bei Inkompatibilität; Anzeige in
  `admin/plugins` und im Manifest).
- Security Advisories und Betroffenheitsbewertung je Version.
- Update-Server / Verfügbarkeits-Check (`update.workdiary.app`).
- Plugin-Kompatibilitätsmatrix (übergreifende Sicht je Kern-Version).
