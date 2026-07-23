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
# Die Dateinamen tragen den Instanznamen (<name>_db_<Stempel>.sql.gz …), damit
# Backups mehrerer Installationen im selben BACKUP_DIR unterscheidbar bleiben.
#
# Optional überschreibbar per Env oder /etc/workdiary-backup.conf
# (chmod 600; legt scripts/install-system.sh mit Defaults an):
#   APP_DIR                – Default: Elternverzeichnis dieses Skripts
#   BACKUP_DIR             – Default: /var/backups/workdiary
#   BACKUP_NAME            – Instanzname im Dateinamen; Default: APP_NAME aus
#                            der App-.env (kleingeschrieben/slugifiziert)
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

# Instanzname für die Dateinamen: Override → APP_NAME, dateinamensicher
# gemacht (kleingeschrieben, alles außer a-z0-9._- wird zu '-').
BACKUP_NAME="${BACKUP_NAME:-$(env_get APP_NAME)}"
BACKUP_NAME=$(printf '%s' "$BACKUP_NAME" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9._-]+/-/g; s/^[-.]+//; s/[-.]+$//')
BACKUP_NAME="${BACKUP_NAME:-workdiary}"

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

DB_FILE="$BACKUP_DIR/${BACKUP_NAME}_db_${TS}.sql.gz"
STORAGE_FILE="$BACKUP_DIR/${BACKUP_NAME}_storage_${TS}.tar.gz"
ENV_COPY="$BACKUP_DIR/${BACKUP_NAME}_env_${TS}.txt"
MANIFEST="$BACKUP_DIR/${BACKUP_NAME}_manifest_${TS}.sha256"

# Überlappungsschutz je Instanz: hängt ein Vorlauf, bricht dieser Lauf sofort
# ab — andere Instanzen im selben BACKUP_DIR bleiben unbetroffen.
exec 9>"$BACKUP_DIR/.${BACKUP_NAME}.lock"
flock -n 9 || { echo "FEHLER: Es läuft bereits ein Backup (Lock $BACKUP_DIR/.${BACKUP_NAME}.lock)." >&2; exit 1; }

# Passwort NIE auf der Kommandozeile (Prozessliste!) — defaults-extra-file.
# Bei Fehlschlag zusätzlich die unvollständigen Dateien dieses Laufs entfernen
# (das Manifest entsteht zuletzt — nur vollständige Läufe haben eines).
MYCNF=$(mktemp)
cleanup() {
  local rc=$?
  rm -f "$MYCNF"
  if [[ $rc -ne 0 ]]; then
    rm -f "$DB_FILE" "$STORAGE_FILE" "$ENV_COPY" "$MANIFEST"
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
  "$DB_NAME" | gzip > "$DB_FILE"

# 2) Storage-Nutzdaten (Anhänge, Dokumente, Exporte)
tar -C "$APP_DIR" -czf "$STORAGE_FILE" storage/app

# 3) .env separat (geschützt — enthält den APP_KEY: ohne ihn sind alle
#    encrypted-Felder des DB-Dumps unbrauchbar)
cp "$ENV_FILE" "$ENV_COPY"
chmod 600 "$ENV_COPY"

# 4) Hash + Manifest
sha256sum "$DB_FILE" "$STORAGE_FILE" "$ENV_COPY" > "$MANIFEST"

# 5) Heartbeat an WorkDiary melden (nicht fatal — das Backup selbst ist fertig)
HEARTBEAT_URL="${BACKUP_HEARTBEAT_URL:-${APP_URL:+$APP_URL/admin/backup/heartbeat}}"
if [[ -n "$HEARTBEAT_URL" && -n "$BACKUP_HEARTBEAT_TOKEN" ]]; then
  MANIFEST_HASH=$(sha256sum "$MANIFEST" | cut -d' ' -f1)
  SIZE_BYTES=$(stat -c%s "$DB_FILE")
  HOST=$(hostname -f 2>/dev/null || hostname)

  if ! curl -fsS -X POST \
    -H "Authorization: Bearer $BACKUP_HEARTBEAT_TOKEN" \
    --data-urlencode "manifest_sha256=$MANIFEST_HASH" \
    --data-urlencode "size_bytes=$SIZE_BYTES" \
    --data-urlencode "source=$HOST" \
    "$HEARTBEAT_URL" > /dev/null; then
    echo "WARNUNG: Heartbeat an $HEARTBEAT_URL fehlgeschlagen (Backup selbst ok)." >&2
  fi
else
  echo "HINWEIS: Heartbeat übersprungen (BACKUP_HEARTBEAT_TOKEN bzw. APP_URL fehlt) — der Lauf erscheint NICHT auf der Backup-Statusseite. Token erzeugen: php artisan workdiary:backup:rotate-token"
fi

# 6) Retention: alte Stände DIESER Instanz aufräumen (dazu unpräfixte
#    Altbestände aus Skriptversionen ohne Instanznamen)
find "$BACKUP_DIR" -maxdepth 1 -type f \
  \( -name "${BACKUP_NAME}_db_*.sql.gz" -o -name "${BACKUP_NAME}_storage_*.tar.gz" \
     -o -name "${BACKUP_NAME}_env_*.txt" -o -name "${BACKUP_NAME}_manifest_*.sha256" \
     -o -name 'db_*.sql.gz' -o -name 'storage_*.tar.gz' -o -name 'env_*.txt' -o -name 'manifest_*.sha256' \) \
  -mtime +"$BACKUP_KEEP_DAYS" -delete

echo "Backup abgeschlossen: $BACKUP_DIR (Instanz $BACKUP_NAME, Stempel $TS, Retention ${BACKUP_KEEP_DAYS} Tage)."
