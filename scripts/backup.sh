#!/usr/bin/env bash
#
# WorkDiary Backup-Vorlage (MVP-046 §4).
#
# Erwartet folgende Env-Variablen:
#   DB_USER, DB_PASS, DB_NAME    – MySQL/MariaDB-Zugang
#   BACKUP_DIR                   – Zielverzeichnis (default /var/backups/workdiary)
#   APP_DIR                      – WorkDiary-Installationsverzeichnis (default /var/www/workdiary)
#   BACKUP_HEARTBEAT_URL         – z. B. https://workdiary.example.org/admin/backup/heartbeat
#   BACKUP_HEARTBEAT_TOKEN       – Bearer-Token (siehe `php artisan workdiary:backup:rotate-token`)
#
# Diese Datei ist als Template gedacht und MUSS an die jeweilige
# Umgebung (Pfade, DB-System, Retention) angepasst werden.

set -euo pipefail

TS=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-/var/backups/workdiary}"
APP_DIR="${APP_DIR:-/var/www/workdiary}"
mkdir -p "$BACKUP_DIR"

# 1) Datenbank
mysqldump --single-transaction --quick \
  -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  | gzip > "$BACKUP_DIR/db_${TS}.sql.gz"

# 2) Storage
tar -C "$APP_DIR" -czf "$BACKUP_DIR/storage_${TS}.tar.gz" storage/app

# 3) .env (separat, geschützt)
cp "$APP_DIR/.env" "$BACKUP_DIR/env_${TS}.txt"
chmod 600 "$BACKUP_DIR/env_${TS}.txt"

# 4) Hash + Manifest
sha256sum "$BACKUP_DIR/db_${TS}.sql.gz" \
          "$BACKUP_DIR/storage_${TS}.tar.gz" \
          "$BACKUP_DIR/env_${TS}.txt" \
  > "$BACKUP_DIR/manifest_${TS}.sha256"

# 5) Heartbeat an WorkDiary melden (optional, aber empfohlen)
if [[ -n "${BACKUP_HEARTBEAT_URL:-}" && -n "${BACKUP_HEARTBEAT_TOKEN:-}" ]]; then
  MANIFEST_HASH=$(sha256sum "$BACKUP_DIR/manifest_${TS}.sha256" | cut -d' ' -f1)
  SIZE_BYTES=$(stat -c%s "$BACKUP_DIR/db_${TS}.sql.gz")
  HOST=$(hostname -f 2>/dev/null || hostname)

  curl -fsS -X POST \
    -H "Authorization: Bearer $BACKUP_HEARTBEAT_TOKEN" \
    --data-urlencode "manifest_sha256=$MANIFEST_HASH" \
    --data-urlencode "size_bytes=$SIZE_BYTES" \
    --data-urlencode "source=$HOST" \
    "$BACKUP_HEARTBEAT_URL" > /dev/null
fi

echo "Backup abgeschlossen: $BACKUP_DIR (Stempel $TS)."
