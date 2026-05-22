# Backup-Hinweise und Restore-Anleitung (lokale Installation)

Status: Aktiv (MVP-046, Issue #45) • Quelle:
[Feature 017 — Backup / Restore / Disaster Recovery](features/017-backup-restore-disaster-recovery.md).

## 1. Zielgruppe

Betreiber einer **selbst-gehosteten** WorkDiary-Installation
(Bare-Metal, VM oder Container) auf Linux. SaaS-Betrieb ist hier
nicht Gegenstand.

## 2. Was muss gesichert werden

1. **Datenbank** (MySQL/MariaDB oder PostgreSQL).
2. **Storage-Verzeichnis** `storage/app/` inkl. Unterordner
   `attachments/`, `protocols/`, `exports/`.
3. **`.env`** (enthält `APP_KEY`, ohne `APP_KEY` sind verschlüsselte
   Felder unbrauchbar).
4. Optional: `bootstrap/cache/` und `storage/logs/` (für Forensik).

Nicht sichern: `node_modules/`, `vendor/` (aus Composer/NPM
reproduzierbar), `storage/framework/cache/` (laufzeitspezifisch).

## 3. Empfohlene Backup-Strategie (Minimal)

- **Täglich**: Dump der Datenbank + Tar des `storage/app/`-Ordners.
- **Stündlich** (für DB): Binlog-Replikation oder PITR-Snapshot.
- **Wöchentlich**: Voll-Sicherung + Restore-Test (siehe §6).
- **Retention**: 7 tägliche, 4 wöchentliche, 12 monatliche
  Backups. (3-2-1-Regel beachten.)

## 4. Beispiel-Script `scripts/backup.sh`

Liegt im Repository als Vorlage; ist **anzupassen**:

```bash
#!/usr/bin/env bash
set -euo pipefail
TS=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/var/backups/workdiary
mkdir -p "$BACKUP_DIR"

# 1) DB
mysqldump --single-transaction --quick \
  -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "$BACKUP_DIR/db_${TS}.sql.gz"

# 2) Storage
tar -C /var/www/workdiary -czf "$BACKUP_DIR/storage_${TS}.tar.gz" storage/app

# 3) .env (separat, geschützt)
cp /var/www/workdiary/.env "$BACKUP_DIR/env_${TS}.txt"
chmod 600 "$BACKUP_DIR/env_${TS}.txt"

# 4) Hash + Manifest
sha256sum "$BACKUP_DIR/db_${TS}.sql.gz" \
          "$BACKUP_DIR/storage_${TS}.tar.gz" \
          "$BACKUP_DIR/env_${TS}.txt" \
  > "$BACKUP_DIR/manifest_${TS}.sha256"
```

`scripts/backup.sh` wird per `cron` (z. B. nightly) ausgeführt.
Ergebnis-Manifest dokumentiert Integritäts-Hashes.

## 5. Backup-Heartbeat in WorkDiary

`scripts/backup.sh` ruft am Ende einen signed Endpoint auf:

```bash
curl -fsS -X POST -H "Authorization: Bearer $BACKUP_HEARTBEAT_TOKEN" \
  https://workdiary.example.org/admin/backup/heartbeat \
  -d "manifest_sha256=$(sha256sum $BACKUP_DIR/manifest_${TS}.sha256 | cut -d' ' -f1)" \
  -d "size_bytes=$(stat -c%s $BACKUP_DIR/db_${TS}.sql.gz)"
```

Endpoint speichert in `backup_heartbeats(occurred_at, size_bytes,
manifest_hash, source)` und feuert
[Diagnose-Seite §3.7](diagnose-seite.md). Fehlt der Heartbeat >
26 h, zeigt Diagnostics „warn", > 72 h „critical".

## 6. Restore-Anleitung

### 6.1 Voraussetzungen

- Frische DB-Instanz mit ausreichend Platz.
- WorkDiary-Codebase in passender Version (gleiche Version wie zum
  Backup-Zeitpunkt — sonst Migration nach Restore nötig).
- `.env` aus Backup (insbesondere `APP_KEY`).

### 6.2 Schritte

```bash
# 1) DB restoren
gunzip < db_YYYYMMDD_HHMMSS.sql.gz | mysql -u root -p "$DB_NAME"

# 2) Storage restoren
tar -C /var/www/workdiary -xzf storage_YYYYMMDD_HHMMSS.tar.gz

# 3) .env zurueckspielen
cp env_YYYYMMDD_HHMMSS.txt /var/www/workdiary/.env
chmod 600 /var/www/workdiary/.env

# 4) Caches loeschen
php artisan optimize:clear

# 5) Migrationen (nur wenn neuere Version)
php artisan migrate --force

# 6) Heartbeat-Token erneuern (Token darf nicht im Backup sein)
php artisan workdiary:backup:rotate-token

# 7) Smoke-Test
php artisan workdiary:diagnostics --format=text
```

### 6.3 Restore-Test (verpflichtend)

Mindestens **monatlich** Restore in eine separate Test-Umgebung:

1. Restore gemäß §6.2.
2. Login mit bekanntem Test-Admin.
3. Vergleich Anzahl `diary_entries`, `time_entries`, `attachments`
   mit Pre-Backup-Snapshot (in Backup-Manifest mitgespeichert).
4. Dokumentation des Tests im Org-Dokumenten-Bereich
   (Vorlage `restore-test-template.md`).
5. Bei Erfolg: Audit-Event `backup.restoreTested`.

## 7. Sicherheit

- Backups verschlüsseln (z. B. `age` oder GPG) vor Offsite-
  Transport.
- Mindestens ein Offsite-Backup (anderes Rechenzentrum, anderer
  Provider).
- Zugriff auf Backup-Speicher mit eigenem Account, **nicht** mit
  WorkDiary-App-Credentials.

## 8. Akzeptanzkriterien

1. `scripts/backup.sh` als Vorlage im Repo.
2. Endpoint `/admin/backup/heartbeat` mit Token-Auth +
   `backup_heartbeats`-Tabelle.
3. Diagnose-Seite zeigt Backup-Status (Verweis §3.7 MVP-044).
4. Doku `docs/backup-restore.md` (diese Datei) enthält Schritt-für-
   Schritt-Restore.
5. `restore-test-template.md` als Vorlage für Test-Protokoll.
6. Audit-Events `backup.heartbeatReceived`,
   `backup.restoreTested`, `backup.tokenRotated`.

## 9. Out-of-scope (MVP-046)

- Eingebautes Backup-Tool (App-internes Scheduler-Backup).
- Cloud-Storage-Adapter (S3, Azure Blob) als First-Class.
- Inkrementelle Backups.

## 10. Folge

- MVP-047 Lizenzstatus & Feature-Flags.
