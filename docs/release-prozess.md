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
- Signierte Release-Metadaten (Build-Hash, Prüfsummen).
- Security Advisories und Betroffenheitsbewertung je Version.
- Update-Server / Verfügbarkeits-Check (`update.workdiary.app`).
- Plugin-Kompatibilitätsmatrix je Kern-Version.
