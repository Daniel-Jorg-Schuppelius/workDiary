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

# Konfigurationsdatei per BACKUP_CONF wählbar (Cron mehrerer Instanzen zeigt je
# auf ihre eigene /etc/workdiary-<instanz>-backup.conf); Default systemweit.
# shellcheck disable=SC1091
BACKUP_CONF="${BACKUP_CONF:-/etc/workdiary-backup.conf}"
[[ -r "$BACKUP_CONF" ]] && source "$BACKUP_CONF"

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
  mysql|mariadb)
    [[ -n "$DB_NAME" && -n "$DB_USER" ]] || { echo "FEHLER: DB_DATABASE/DB_USERNAME fehlen in $ENV_FILE." >&2; exit 1; }
    ;;
  sqlite)
    DB_NAME="${DB_NAME:-$APP_DIR/database/database.sqlite}"
    [[ -f "$DB_NAME" ]] || { echo "FEHLER: SQLite-Datei $DB_NAME nicht gefunden." >&2; exit 1; }
    ;;
  *) echo "FEHLER: DB_CONNECTION='$DB_CONNECTION' — unterstützt: mysql, mariadb, sqlite." >&2; exit 1 ;;
esac

BACKUP_DIR="${BACKUP_DIR:-/var/backups/workdiary}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
TS=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"
# 700 ist erwünscht (verhindert das Auflisten fremder Backups), aber nicht
# überall durchsetzbar: gehört das Verzeichnis einem anderen Nutzer (mehrere
# Instanzen teilen sich den Default /var/backups/workdiary), liegt es auf einem
# Mount ohne Rechteverwaltung (CIFS/NFS) oder trägt es ein immutable-Flag,
# scheitert chmod mit EPERM — als root ebenso. Das darf den Lauf NICHT abbrechen:
# die Backup-Dateien selbst entstehen durch `umask 077` als 600, die .env-Kopie
# zusätzlich explizit. Offene Verzeichnisrechte werden nur gemeldet.
if ! chmod 700 "$BACKUP_DIR" 2>/dev/null; then
  DIR_PERM="$(stat -c '%a' "$BACKUP_DIR" 2>/dev/null || echo '???')"
  DIR_OWNER="$(stat -c '%U' "$BACKUP_DIR" 2>/dev/null || echo '?')"
  if [[ "${DIR_PERM: -2}" != "00" ]]; then
    echo "WARNUNG: $BACKUP_DIR (Eigentümer $DIR_OWNER, Rechte $DIR_PERM) lässt sich nicht auf 700 setzen und ist für Gruppe/Andere sichtbar." >&2
    echo "         Die Backup-Dateien selbst sind 600, das Verzeichnis sollte dennoch geschlossen werden: chmod 700 $BACKUP_DIR" >&2
  fi
fi
# Schreibrecht früh und klar prüfen — sonst scheitert erst der Dump mit einer
# Fehlermeldung, die nicht auf die Ursache zeigt.
if [[ ! -w "$BACKUP_DIR" ]]; then
  echo "FEHLER: $BACKUP_DIR ist für $(id -un) nicht beschreibbar (Eigentümer $(stat -c '%U' "$BACKUP_DIR" 2>/dev/null || echo '?'), Rechte $(stat -c '%a' "$BACKUP_DIR" 2>/dev/null || echo '?'))." >&2
  echo "       Entweder das Verzeichnis dem ausführenden Nutzer übereignen oder in der Konfiguration in ${BACKUP_CONF:-/etc/workdiary-backup.conf} ein eigenes BACKUP_DIR für diese Instanz setzen." >&2
  exit 1
fi

# SQLite-Backups sind gzippte DB-Dateien (.sqlite.gz), MySQL/MariaDB SQL-Dumps.
if [[ "$DB_CONNECTION" == "sqlite" ]]; then
  DB_FILE="$BACKUP_DIR/${BACKUP_NAME}_db_${TS}.sqlite.gz"
else
  DB_FILE="$BACKUP_DIR/${BACKUP_NAME}_db_${TS}.sql.gz"
fi
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
TMP_SQLITE=""
TAR_STAGING_FILE=""
TAR_STAGING_DIR=""
cleanup() {
  local rc=$?
  # Aufräumen darf nie an `set -e` scheitern: bricht der Trap mittendrin ab,
  # verschluckt er die Fehlermeldung UND setzt den Exit-Code auf 1 — ein
  # erfolgreicher Lauf sieht für den Aufrufer (deploy.sh) dann wie ein Fehler
  # aus. Genau das tat das rmdir, dessen Verzeichnis der Normalpfad bereits
  # entfernt hat.
  set +e
  rm -f "$MYCNF" ${TMP_SQLITE:+"$TMP_SQLITE"} ${TAR_STAGING_FILE:+"$TAR_STAGING_FILE"}
  [[ -n "$TAR_STAGING_DIR" ]] && rmdir "$TAR_STAGING_DIR" 2>/dev/null
  if [[ $rc -ne 0 ]]; then
    rm -f "$DB_FILE" "$STORAGE_FILE" "$ENV_COPY" "$MANIFEST"
    echo "FEHLER: Backup-Lauf $TS abgebrochen — unvollständige Dateien entfernt." >&2
  fi
  return 0
}
trap cleanup EXIT
PW_ESCAPED="${DB_PASS//\\/\\\\}"; PW_ESCAPED="${PW_ESCAPED//\"/\\\"}"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword="%s"\n' \
  "$DB_HOST" "$DB_PORT" "$DB_USER" "$PW_ESCAPED" > "$MYCNF"

# 1) Datenbank — MySQL/MariaDB per Dump (inkl. Routinen/Trigger/Events),
#    SQLite per Online-Backup (sqlite3 .backup ist konsistent bei laufender App)
if [[ "$DB_CONNECTION" == "sqlite" ]]; then
  TMP_SQLITE=$(mktemp)
  if command -v sqlite3 >/dev/null 2>&1; then
    sqlite3 "$DB_NAME" ".backup '$TMP_SQLITE'"
  else
    echo "WARNUNG: sqlite3 fehlt — Rohkopie der DB-Datei (bei laufenden Schreibzugriffen inkonsistenzgefährdet)." >&2
    cp "$DB_NAME" "$TMP_SQLITE"
  fi
  gzip < "$TMP_SQLITE" > "$DB_FILE"
  rm -f "$TMP_SQLITE"; TMP_SQLITE=""
else
  mysqldump --defaults-extra-file="$MYCNF" \
    --single-transaction --quick --routines --triggers --events \
    "$DB_NAME" | gzip > "$DB_FILE"
fi

# 2) Storage-Nutzdaten (Anhänge, Dokumente, Exporte).
# Backup-Artefakte ausnehmen: liegt BACKUP_DIR in storage/app (Default des
# deploy.sh-Pre-Backups), liest tar sonst sein eigenes wachsendes Archiv mit
# ("Datei hat sich beim Lesen geändert" → Abbruch); predeploy-backups gehören
# auch sonst nicht in Folge-Backups (Archiv-in-Archiv-Wachstum).
TAR_EXCLUDES=(--exclude 'storage/app/private/predeploy-backups')
APP_DIR_ABS="$(cd "$APP_DIR" && pwd)"
BACKUP_DIR_ABS="$(cd "$BACKUP_DIR" && pwd)"
if [[ "$BACKUP_DIR_ABS/" == "$APP_DIR_ABS/storage/app/"* ]]; then
  TAR_EXCLUDES+=(--exclude "${BACKUP_DIR_ABS#"$APP_DIR_ABS/"}")
fi

# Erst NEBEN den gesicherten Baum schreiben, dann hineinschieben: solange das
# wachsende Archiv in storage/app liegt, hängt die Sicherung davon ab, dass
# jedes --exclude sitzt. Ein Ziel außerhalb von storage/app schließt das
# strukturell aus — auch für Aufrufer mit eigenem BACKUP_DIR.
TAR_STAGING_DIR="$APP_DIR_ABS/storage/backup-staging"
mkdir -p "$TAR_STAGING_DIR"
TAR_STAGING_FILE="$TAR_STAGING_DIR/$(basename "$STORAGE_FILE").part"
rm -f "$TAR_STAGING_FILE"

# tar meldet mit Exit 1 „Datei hat sich beim Lesen geändert". Auf einem
# laufenden System ist das der Regelfall (Logs, Uploads, Sessions) und kein
# Fehler: die betroffene Datei steckt mit dem Stand ihres Lesezeitpunkts im
# Archiv. Nur ab Exit 2 ist das Archiv unbrauchbar. Ohne diese Unterscheidung
# reißt `set -e` jeden Lauf ab, sobald jemand die Anwendung benutzt.
set +e
tar -C "$APP_DIR" "${TAR_EXCLUDES[@]}" --warning=no-file-changed -czf "$TAR_STAGING_FILE" storage/app
TAR_RC=$?
set -e
if [[ $TAR_RC -eq 1 ]]; then
  echo "HINWEIS: Während der Sicherung haben sich Dateien geändert (laufender Betrieb) — Archiv ist gültig, betroffene Dateien mit dem Stand des Lesezeitpunkts." >&2
elif [[ $TAR_RC -ne 0 ]]; then
  rm -f "$TAR_STAGING_FILE"
  echo "FEHLER: tar brach mit Code $TAR_RC ab — Archiv verworfen." >&2
  exit $TAR_RC
fi
mv "$TAR_STAGING_FILE" "$STORAGE_FILE"
rmdir "$TAR_STAGING_DIR" 2>/dev/null || true

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
  \( -name "${BACKUP_NAME}_db_*.sql.gz" -o -name "${BACKUP_NAME}_db_*.sqlite.gz" -o -name "${BACKUP_NAME}_storage_*.tar.gz" \
     -o -name "${BACKUP_NAME}_env_*.txt" -o -name "${BACKUP_NAME}_manifest_*.sha256" \
     -o -name 'db_*.sql.gz' -o -name 'storage_*.tar.gz' -o -name 'env_*.txt' -o -name 'manifest_*.sha256' \) \
  -mtime +"$BACKUP_KEEP_DAYS" -delete

echo "Backup abgeschlossen: $BACKUP_DIR (Instanz $BACKUP_NAME, Stempel $TS, Retention ${BACKUP_KEEP_DAYS} Tage)."
