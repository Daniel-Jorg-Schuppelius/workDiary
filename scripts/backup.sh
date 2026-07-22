#!/usr/bin/env bash
#
# WorkDiary Backup (MVP-046 §4) — dynamisch: alles Nötige wird aus der
# Installation selbst ermittelt. APP_DIR aus dem Skriptpfad (dieses Skript
# liegt in <app>/scripts/), DB-Zugang + APP_URL aus der .env, Heartbeat-URL
# aus APP_URL + /admin/backup/heartbeat. Ein Cron-Eintrag ohne weitere
# Konfiguration genügt:
#
#   0 23 * * * root /pfad/zur/app/scripts/backup.sh >> /var/log/workdiary-backup.log 2>&1
#
# Optional überschreibbar per Env oder /etc/workdiary-backup.conf (chmod 600):
#   APP_DIR                – Default: Elternverzeichnis dieses Skripts
#   BACKUP_DIR             – Default: /var/backups/workdiary
#   BACKUP_KEEP_DAYS       – Default: 14 (ältere Stände werden gelöscht)
#   BACKUP_HEARTBEAT_URL   – Default: <APP_URL>/admin/backup/heartbeat
#   BACKUP_HEARTBEAT_TOKEN – Default: BACKUP_HEARTBEAT_TOKEN aus der App-.env
#                            (dorthin schreibt `php artisan workdiary:backup:rotate-token`);
#                            ohne Token wird kein Heartbeat gesendet

set -euo pipefail
umask 077

# shellcheck disable=SC1091
[[ -r /etc/workdiary-backup.conf ]] && source /etc/workdiary-backup.conf

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(dirname "$SCRIPT_DIR")}"
ENV_FILE="$APP_DIR/.env"
[[ -r "$ENV_FILE" ]] || { echo "FEHLER: $ENV_FILE nicht lesbar — APP_DIR korrekt?" >&2; exit 1; }

# Wert aus der .env lesen — wie Laravels dotenv: letzter Treffer gewinnt,
# umgebende Quotes entfernen, in doppelt-quoteten Werten \" und \\ auflösen.
env_get() {
  local raw val
  raw=$(grep -E "^${1}=" "$ENV_FILE" | tail -n1 | cut -d= -f2- || true)
  if [[ "$raw" == \"*\" && ${#raw} -ge 2 ]]; then
    val="${raw:1:${#raw}-2}"
    val="${val//\\\"/\"}"
    val="${val//\\\\/\\}"
  elif [[ "$raw" == \'*\' && ${#raw} -ge 2 ]]; then
    val="${raw:1:${#raw}-2}"
  else
    val="$raw"
  fi
  printf '%s' "$val"
}

DB_CONNECTION="$(env_get DB_CONNECTION)"
DB_HOST="$(env_get DB_HOST)";     DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_get DB_PORT)";     DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_get DB_DATABASE)"
DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"
APP_URL="$(env_get APP_URL)";     APP_URL="${APP_URL%/}"
BACKUP_HEARTBEAT_TOKEN="${BACKUP_HEARTBEAT_TOKEN:-$(env_get BACKUP_HEARTBEAT_TOKEN)}"

case "$DB_CONNECTION" in
  mysql|mariadb) ;;
  *) echo "FEHLER: DB_CONNECTION='$DB_CONNECTION' — dieses Skript sichert MySQL/MariaDB." >&2; exit 1 ;;
esac
[[ -n "$DB_NAME" && -n "$DB_USER" ]] || { echo "FEHLER: DB_DATABASE/DB_USERNAME fehlen in $ENV_FILE." >&2; exit 1; }

BACKUP_DIR="${BACKUP_DIR:-/var/backups/workdiary}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
TS=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# Überlappungsschutz: hängt ein Vorlauf, bricht dieser Lauf sofort ab.
exec 9>"$BACKUP_DIR/.lock"
flock -n 9 || { echo "FEHLER: Es läuft bereits ein Backup (Lock $BACKUP_DIR/.lock)." >&2; exit 1; }

# Passwort NIE auf der Kommandozeile (Prozessliste!) — defaults-extra-file.
# Bei Fehlschlag zusätzlich die unvollständigen Dateien dieses Laufs entfernen
# (das Manifest entsteht zuletzt — nur vollständige Läufe haben eines).
MYCNF=$(mktemp)
cleanup() {
  local rc=$?
  rm -f "$MYCNF"
  if [[ $rc -ne 0 ]]; then
    rm -f "$BACKUP_DIR/db_${TS}.sql.gz" "$BACKUP_DIR/storage_${TS}.tar.gz" \
          "$BACKUP_DIR/env_${TS}.txt" "$BACKUP_DIR/manifest_${TS}.sha256"
    echo "FEHLER: Backup-Lauf $TS abgebrochen — unvollständige Dateien entfernt." >&2
  fi
}
trap cleanup EXIT
PW_ESCAPED="${DB_PASS//\\/\\\\}"; PW_ESCAPED="${PW_ESCAPED//\"/\\\"}"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' \
  "$DB_HOST" "$DB_PORT" "$DB_USER" "$PW_ESCAPED" > "$MYCNF"

# 1) Datenbank (inkl. Routinen/Trigger/Events)
mysqldump --defaults-extra-file="$MYCNF" \
  --single-transaction --quick --routines --triggers --events \
  "$DB_NAME" | gzip > "$BACKUP_DIR/db_${TS}.sql.gz"

# 2) Storage-Nutzdaten (Anhänge, Dokumente, Exporte)
tar -C "$APP_DIR" -czf "$BACKUP_DIR/storage_${TS}.tar.gz" storage/app

# 3) .env separat (geschützt — enthält den APP_KEY: ohne ihn sind alle
#    encrypted-Felder des DB-Dumps unbrauchbar)
cp "$ENV_FILE" "$BACKUP_DIR/env_${TS}.txt"
chmod 600 "$BACKUP_DIR/env_${TS}.txt"

# 4) Hash + Manifest
sha256sum "$BACKUP_DIR/db_${TS}.sql.gz" \
          "$BACKUP_DIR/storage_${TS}.tar.gz" \
          "$BACKUP_DIR/env_${TS}.txt" \
  > "$BACKUP_DIR/manifest_${TS}.sha256"

# 5) Heartbeat an WorkDiary melden (nicht fatal — das Backup selbst ist fertig)
HEARTBEAT_URL="${BACKUP_HEARTBEAT_URL:-${APP_URL:+$APP_URL/admin/backup/heartbeat}}"
if [[ -n "$HEARTBEAT_URL" && -n "$BACKUP_HEARTBEAT_TOKEN" ]]; then
  MANIFEST_HASH=$(sha256sum "$BACKUP_DIR/manifest_${TS}.sha256" | cut -d' ' -f1)
  SIZE_BYTES=$(stat -c%s "$BACKUP_DIR/db_${TS}.sql.gz")
  HOST=$(hostname -f 2>/dev/null || hostname)

  if ! curl -fsS -X POST \
    -H "Authorization: Bearer $BACKUP_HEARTBEAT_TOKEN" \
    --data-urlencode "manifest_sha256=$MANIFEST_HASH" \
    --data-urlencode "size_bytes=$SIZE_BYTES" \
    --data-urlencode "source=$HOST" \
    "$HEARTBEAT_URL" > /dev/null; then
    echo "WARNUNG: Heartbeat an $HEARTBEAT_URL fehlgeschlagen (Backup selbst ok)." >&2
  fi
fi

# 6) Retention: alte Stände aufräumen
find "$BACKUP_DIR" -maxdepth 1 -type f \
  \( -name 'db_*.sql.gz' -o -name 'storage_*.tar.gz' -o -name 'env_*.txt' -o -name 'manifest_*.sha256' \) \
  -mtime +"$BACKUP_KEEP_DAYS" -delete

echo "Backup abgeschlossen: $BACKUP_DIR (Stempel $TS, Retention ${BACKUP_KEEP_DAYS} Tage)."
