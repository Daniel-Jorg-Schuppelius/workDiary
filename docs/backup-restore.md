# Backup & Restore — Betriebshandbuch

Zielgruppe: Betreiber einer **selbst gehosteten** WorkDiary-Installation
(Bare-Metal, VM oder Container) auf Linux. Dieses Handbuch beschreibt die
beiden Backup-Wege der Plattform und die Wiederherstellung:

1. **Externes Backup + Heartbeat-Überwachung** — das eigentliche Backup läuft
   außerhalb von WorkDiary (Cron + `scripts/backup.sh`); die App überwacht nur,
   dass es regelmäßig passiert.
2. **Verschlüsselte Cloud-Backupziele** — app-internes Snapshot-Backup
   (Datenbank + Storage) mit Ende-zu-Ende-Verschlüsselung zu Dropbox,
   OneDrive/SharePoint oder Google Drive.

Der Status beider Wege ist in der App unter **Administration → Backup &
Restore** sichtbar (letzte Sicherung je Quelle, Frische-Warnungen,
Restore-Test-Register).

## 1. Was gesichert werden muss

1. **Datenbank** (MySQL/MariaDB oder PostgreSQL).
2. **Storage-Verzeichnis** `storage/app/` (Belege, Dokumente, Uploads).
3. **`.env`** — enthält den `APP_KEY`; ohne ihn sind verschlüsselte Felder
   (PII, 2FA, Datenschutz-Fälle) unwiederbringlich verloren.

Nicht sichern: `vendor/`, `node_modules/` (reproduzierbar),
`storage/framework/cache/` und andere Laufzeit-Artefakte.

Empfohlene Aufbewahrung: 7 tägliche, 4 wöchentliche, 12 monatliche
Sicherungen; mindestens ein Offsite-Backup (3-2-1-Regel).

## 2. Externes Backup + Heartbeat

### 2.1 Backup-Skript

`scripts/backup.sh` (DB-Sicherung, Storage-Tar, `.env`-Kopie,
SHA-256-Manifest) ermittelt seine Konfiguration **selbst** aus der
Installation: `APP_DIR` aus dem eigenen Skriptpfad, DB-Zugang, `APP_URL` und
Heartbeat-Token aus der App-`.env`. MySQL/MariaDB wird per Dump gesichert
(inkl. Routinen/Trigger, Endung `.sql.gz`), SQLite per konsistentem
Online-Backup der DB-Datei (`sqlite3 .backup`, Endung `.sqlite.gz`). Ein
Cron-Eintrag genügt:

```cron
0 23 * * * root /pfad/zur/app/scripts/backup.sh >> /var/log/workdiary-backup.log 2>&1
```

Der Zeitpunkt muss in die Betriebszeit des Servers fallen (kein
Nachhol-Verhalten bei ausgeschaltetem Server). Die Dateinamen tragen den
Instanznamen aus `APP_NAME` (kleingeschrieben/slugifiziert, z. B.
`workdiary_db_20260723_230000.sql.gz`) — so bleiben Backups mehrerer
Installationen im selben Zielverzeichnis unterscheidbar. Optionale Overrides
(`BACKUP_DIR`, `BACKUP_NAME` = Instanzname im Dateinamen,
`BACKUP_KEEP_DAYS` = Retention in Tagen, Default 14,
`BACKUP_HEARTBEAT_URL`/`-TOKEN`) per Env oder `/etc/workdiary-backup.conf`
(chmod 600) — Cron-Eintrag und Konfigurationsdatei legt
[`scripts/install-system.sh`](systemdienste.md) an (`--backup-time`,
`--backup-dir`, `--backup-keep-days`). Sicherheitsverhalten: DB-Passwort nur über eine temporäre
`defaults-extra-file` (nie in der Prozessliste), `flock`-Überlappungsschutz,
fehlgeschlagene Läufe räumen ihre unvollständigen Dateien weg — nur Läufe
mit Manifest sind vollständig.

### 2.2 Heartbeat einrichten

Backups werden **nicht manuell in der Oberfläche registriert** — das
Backup-Skript meldet jeden erfolgreichen Lauf per Heartbeat; danach erscheint
die Quelle automatisch auf der Statusseite.

- Token erzeugen: `php artisan workdiary:backup:rotate-token` schreibt
  `BACKUP_HEARTBEAT_TOKEN` in die `.env` — `scripts/install-system.sh` führt
  das automatisch aus, wenn der Token noch fehlt. Ohne gesetzten Token ist der
  Endpoint deaktiviert (HTTP 503) **und `backup.sh` überspringt den Heartbeat**
  — Läufe erscheinen dann nicht auf der Statusseite.
- Endpoint: `POST /admin/backup/heartbeat` mit `Authorization: Bearer <Token>`
  (außerhalb des Login-Stacks, gedrosselt). Felder: `manifest_sha256`
  (64 Hex-Zeichen), `size_bytes`, `source`, `occurred_at` — siehe
  Heartbeat-Block in `scripts/backup.sh`.
- Jeder Eingang landet in `backup_heartbeats` und im Audit-Log
  (`backup.heartbeatReceived`).

### 2.3 Überwachung

- `php artisan workdiary:backup:status` — Gesamtübersicht beider Backup-Wege
  (Heartbeat, Schlüssel, Ziele, Restore-Test) mit Handlungshinweisen;
  Exit-Code 1 bei betriebsverhindernden Lücken (Cron-/CI-tauglich).
- Frische-Schwelle je Quelle: `BACKUP_HEARTBEAT_FRESHNESS_HOURS`
  (Default 26 h). Ältere Heartbeats markiert die Statusseite als „überfällig",
  ganz fehlende als „kein Backup registriert".
- `php artisan workdiary:backup:check-restore` prüft Alter und Größe des
  letzten Heartbeats (für Cron/CI); Plausibilitätsgrenzen über
  `BACKUP_MIN_SIZE_BYTES` und `BACKUP_SIZE_DROP_RATIO`.

## 3. Verschlüsselte Cloud-Backupziele

App-internes Snapshot-Backup mit Client-seitiger Verschlüsselung
(libsodium secretstream, XChaCha20-Poly1305). Ziele werden unter
**Administration → Backupziele** verbunden (Dropbox, OneDrive/SharePoint,
Google Drive); S3/Azure sind spätere Adapter desselben Vertrags.

### 3.1 Schlüssel — vor dem ersten Lauf festlegen

**`BACKUP_MASTER_KEY`** ist der einzige reguläre Entschlüsselungsweg. Er ist
**kein frei wählbarer Text**, sondern ein base64-kodierter 32-Byte-Schlüssel:

```bash
php artisan workdiary:backup:generate-master-key
```

Der Befehl erzeugt den Schlüssel, schreibt ihn in die `.env` und zeigt ihn
einmalig zum Übertragen in den Tresor an. Einen vorhandenen Schlüssel ersetzt
er nur mit `--force` (ein neuer Schlüssel öffnet alte Backups nicht mehr).
Manuelle Alternative: `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`
bzw. `openssl rand -base64 32`, Wert selbst in die `.env` eintragen.

- Bewusst **nicht** der `APP_KEY` (getrennte Geheimnisse für App- und
  Backup-Verschlüsselung).
- **Offline sichern** (Tresor/Passwortmanager) — Verlust ohne Recovery-Key
  macht alle Backups wertlos; ein neuer Schlüssel kann alte Backups nicht
  mehr öffnen.
- Gehört **nie** ins Backup selbst, nie ins Cloudziel, nie in Logs.

**`BACKUP_RECOVERY_PUBLIC_KEY`** (optional, empfohlen): crypto_box-Public-Key
als Notfall-Zweitweg. Schlüsselpaar erzeugen:

```bash
php artisan workdiary:backup:generate-recovery-key
```

Der Befehl schreibt den Public-Key in die `.env` und zeigt den Secret-Key
**einmalig** an — sofort in den Offline-Tresor übernehmen, er wird nirgends
gespeichert. Ohne Recovery-Key warnt die Oberfläche dauerhaft. Wer den
Secret-Key gar nie auf dem Server haben will, erzeugt das Paar auf dem
Admin-Rechner und trägt nur den Public-Key in die `.env` ein:

```bash
php -r '$kp = sodium_crypto_box_keypair();
echo "public: ", base64_encode(sodium_crypto_box_publickey($kp)), PHP_EOL,
     "secret: ", base64_encode(sodium_crypto_box_secretkey($kp)), PHP_EOL;'
```

### 3.2 Betrieb

```bash
php artisan workdiary:backup:run           # Snapshot erstellen, hochladen, committen, Retention anwenden
php artisan workdiary:backup:verify        # Commit-Manifest + Stichproben-Teile der jüngsten Generationen prüfen
php artisan workdiary:backup:restore-test  # Generation isoliert wiederherstellen, Integrität protokollieren
```

Die Befehle sind bewusst nicht im App-Scheduler — per Cron einplanen
(z. B. `run` nightly, `verify` wöchentlich, `restore-test` monatlich).

Wichtige Einstellungen (`config/backup_targets.php`):

| Variable | Default | Bedeutung |
| --- | --- | --- |
| `BACKUP_PART_SIZE` | 128 MiB | Teil-Größe des Snapshot-Splits |
| `BACKUP_RETENTION_DAILY/WEEKLY/MONTHLY` | 7 / 4 / 12 | Generationen je Zeitklasse |
| `BACKUP_FILES_ROOT` | Projektwurzel | Wurzel der Datei-Quellen (`storage/app`) |
| `BACKUP_DB_CONNECTION` | database.default | Dump-Connection, z. B. Read-Replikat |
| `BACKUP_WORK_DIR` | `storage/app/backup-work` | lokales Arbeitsverzeichnis |
| `BACKUP_VERIFY_SAMPLE_PARTS` | 2 | Stichproben-Teile je Verify-Lauf |
| `BACKUP_TAR_BINARY`, `BACKUP_MYSQLDUMP_BINARY`, `BACKUP_PG_DUMP_BINARY` | `tar`/`mysqldump`/`pg_dump` | Binary-Pfade (Preflight prüft Verfügbarkeit) |

## 4. Restore-Anleitung (externes Backup)

Voraussetzungen: frische DB-Instanz, WorkDiary-Codebase in der Version zum
Backup-Zeitpunkt, `.env` aus dem Backup (insbesondere `APP_KEY`).

```bash
# 1) Datenbank  (<name> = Instanzname, siehe Abschnitt 2.1)
gunzip < <name>_db_YYYYMMDD_HHMMSS.sql.gz | mysql -u root -p "$DB_NAME"
# SQLite stattdessen: gunzip < <name>_db_….sqlite.gz > pfad/aus/DB_DATABASE

# 2) Storage
tar -C /var/www/workdiary -xzf <name>_storage_YYYYMMDD_HHMMSS.tar.gz

# 3) .env zurückspielen
cp <name>_env_YYYYMMDD_HHMMSS.txt /var/www/workdiary/.env
chmod 600 /var/www/workdiary/.env

# 4) Caches löschen
php artisan optimize:clear

# 5) Migrationen (nur bei neuerer Codebase-Version)
php artisan migrate --force

# 6) Heartbeat-Token erneuern (der alte Token steckt im Backup)
php artisan workdiary:backup:rotate-token

# 7) Smoke-Test
php artisan system:health
```

Cloud-Generationen werden über `php artisan workdiary:backup:restore-test`
isoliert wiederhergestellt und geprüft; im Notfall (Master-Key verloren)
lässt sich der Datenschlüssel mit dem Recovery-Secret-Key öffnen.

## 5. Restore-Tests (verpflichtend)

Regelmäßig — mindestens innerhalb von `BACKUP_RESTORE_TEST_OVERDUE_DAYS`
(Default 180 Tage), empfohlen monatlich — einen Restore in eine separate
Testumgebung durchführen und protokollieren. Bleibt ein erfolgreicher Test
zu lange aus, warnt die Statusseite.

Der einfachste Weg ist das mitgelieferte Skript (als root, lokaler
MySQL/MariaDB):

```bash
scripts/restore-test.sh                                  # jüngster Stand: prüfen + protokollieren
scripts/restore-test.sh --stamp 20260723_230000 --keep   # bestimmter Stand, Umgebung stehen lassen
```

Es stellt den Backup-Stand isoliert unter `/var/tmp` mit eigener Test-DB und
temporärem DB-User wieder her (die laufende Installation bleibt unberührt),
prüft Manifest, Migrationsstand, Datenbestand und die
APP_KEY-Entschlüsselung und trägt das Ergebnis automatisch ins Register ein
— Fehlschläge ebenso; dann bleiben Test-DB und Arbeitsverzeichnis zur
Diagnose stehen. Mit `--keep` lässt sich die wiederhergestellte Kopie danach
per `artisan serve` anschauen.

Manuelle Tests (z. B. auf einer anderen Maschine, §4) unter
**Backup & Restore → Restore-Test protokollieren** ins Register eintragen
(Datum, Quelle, Ergebnis, Umfang, Dauer) — oder per CLI:
`php artisan workdiary:backup:record-restore-test --source=... --result=passed`.

## 6. Sicherheitsregeln

- Externe Backups vor Offsite-Transport verschlüsseln (z. B. `age`/GPG);
  Cloud-Backupziele sind bereits Ende-zu-Ende-verschlüsselt.
- Zugriff auf den Backup-Speicher mit eigenem Account, **nicht** mit
  WorkDiary-App-Credentials.
- `BACKUP_MASTER_KEY`, Recovery-Secret-Key und `APP_KEY` offline und getrennt
  vom Backup aufbewahren.

## Verweise

- In-App-Hilfe: Topic `admin.backups` (Hilfe-Symbol auf der Statusseite).
- Konzept/Architektur: Feature 017 im Doku-Repo WorkDiary-Architecture
  (`backup-restore.md`, `features/017-backup-restore-disaster-recovery.md`).
