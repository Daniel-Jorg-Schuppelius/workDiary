#!/usr/bin/env bash
#
# WorkDiary Restore-Test (MVP-046 §6) — automatisierter Probe-Restore des
# Skript-Backups in eine ISOLIERTE Umgebung auf diesem Server. Die laufende
# Installation wird nie angefasst.
#
# Ablauf:
#   1. Manifest des Backup-Stands prüfen (sha256sum -c)
#   2. Codebase-Kopie + Storage aus dem Backup in ein Arbeitsverzeichnis
#   3. Test-DB bereitstellen: MySQL/MariaDB als eigene DB + temporärer
#      DB-User, SQLite als Dateikopie im Arbeitsverzeichnis
#   4. .env aus dem Backup, auf die Testumgebung umgebogen (APP_KEY bleibt!)
#   5. Smoke-Tests: Migrationen, Datenbestand, APP_KEY-Entschlüsselung;
#      system:health nur informativ (eine ältere Sicherung meldet dort
#      naturgemäß überfällige Heartbeats)
#   6. Ergebnis ins Register der App (workdiary:backup:record-restore-test)
#   7. Aufräumen — bei Fehlschlag bleiben Arbeitsverzeichnis und Test-DB
#      zur Diagnose stehen (Aufräumbefehle werden ausgegeben)
#
# Aufruf (als root; MySQL/MariaDB braucht Socket-Auth als root,
# SQLite kommt ohne DB-Server aus):
#   scripts/restore-test.sh [--stamp YYYYMMDD_HHMMSS] [--work-dir PFAD]
#                           [--keep] [--no-record]
#
# Optional per Env oder /etc/workdiary-backup.conf: BACKUP_DIR, BACKUP_NAME
# (Defaults wie scripts/backup.sh); APP_DIR, PHP_BIN wie install-system.sh.

set -euo pipefail
umask 077

# shellcheck disable=SC1091
[[ -r /etc/workdiary-backup.conf ]] && source /etc/workdiary-backup.conf

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(dirname "$SCRIPT_DIR")}"
ENV_FILE="$APP_DIR/.env"

STAMP=""
WORK_DIR=""
KEEP=0
RECORD=1

usage() { grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --stamp) shift; STAMP="${1:?--stamp braucht YYYYMMDD_HHMMSS}" ;;
    --work-dir) shift; WORK_DIR="${1:?--work-dir braucht einen Pfad}" ;;
    --keep) KEEP=1 ;;
    --no-record) RECORD=0 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; usage >&2; exit 64 ;;
  esac
  shift
done

fail() { echo "FEHLER: $*" >&2; exit 1; }
note() { echo "  $*"; }

# ---------------------------------------------------------------- Preflight

[[ $EUID -eq 0 ]] || fail "bitte als root ausführen (Test-DB anlegen, Arbeitsverzeichnis unter /var/tmp)."
[[ -r "$ENV_FILE" ]] || fail "$ENV_FILE nicht lesbar — APP_DIR korrekt?"

detect_php() {
  if [[ -n "${PHP_BIN:-}" ]]; then printf '%s' "$PHP_BIN"; return; fi
  local candidate
  for candidate in php php8.4 /usr/bin/php /usr/bin/php8.4; do
    if command -v "$candidate" >/dev/null 2>&1; then command -v "$candidate"; return; fi
  done
  fail "kein PHP-Binary gefunden — PHP_BIN=/pfad/zu/php setzen."
}
PHP_BIN="$(detect_php)"
RUN_USER="${RUN_USER:-$(stat -c %U "$APP_DIR/storage" 2>/dev/null || echo www-data)}"

# .env-Leser + Instanzname: identisch zu scripts/backup.sh.
env_get() {
  local raw val
  raw=$(grep -E "^${1}=" "$ENV_FILE" | tail -n1 | cut -d= -f2- || true)
  if [[ "$raw" == \"*\" && ${#raw} -ge 2 ]]; then
    val="${raw:1:${#raw}-2}"; val="${val//\\\"/\"}"; val="${val//\\\\/\\}"
  elif [[ "$raw" == \'*\' && ${#raw} -ge 2 ]]; then
    val="${raw:1:${#raw}-2}"
  else
    val="$raw"
  fi
  printf '%s' "$val"
}
BACKUP_NAME="${BACKUP_NAME:-$(env_get APP_NAME)}"
BACKUP_NAME=$(printf '%s' "$BACKUP_NAME" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9._-]+/-/g; s/^[-.]+//; s/[-.]+$//')
BACKUP_NAME="${BACKUP_NAME:-workdiary}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/workdiary}"

# DB-Art bestimmt Dump-Format und Restore-Weg (wie scripts/backup.sh).
DB_CONNECTION="$(env_get DB_CONNECTION)"
case "$DB_CONNECTION" in
  mysql|mariadb)
    DB_KIND=mysql
    command -v mysql >/dev/null 2>&1 || fail "mysql-Client fehlt."
    mysql -e "SELECT 1" >/dev/null 2>&1 || fail "mysql als root nicht nutzbar (Socket-Auth prüfen)."
    ;;
  sqlite) DB_KIND=sqlite ;;
  *) fail "DB_CONNECTION='$DB_CONNECTION' — unterstützt: mysql, mariadb, sqlite." ;;
esac

# Backup-Stand wählen: --stamp oder jüngstes Manifest dieser Instanz.
if [[ -z "$STAMP" ]]; then
  MANIFEST=$(ls "$BACKUP_DIR/${BACKUP_NAME}_manifest_"*.sha256 2>/dev/null | sort | tail -n1 || true)
  [[ -n "$MANIFEST" ]] || fail "kein Manifest ${BACKUP_NAME}_manifest_*.sha256 in $BACKUP_DIR — erst ein Backup laufen lassen."
  STAMP=$(basename "$MANIFEST" .sha256); STAMP="${STAMP#"${BACKUP_NAME}"_manifest_}"
else
  MANIFEST="$BACKUP_DIR/${BACKUP_NAME}_manifest_${STAMP}.sha256"
  [[ -f "$MANIFEST" ]] || fail "$MANIFEST nicht gefunden."
fi
if [[ "$DB_KIND" == "sqlite" ]]; then
  DB_DUMP="$BACKUP_DIR/${BACKUP_NAME}_db_${STAMP}.sqlite.gz"
else
  DB_DUMP="$BACKUP_DIR/${BACKUP_NAME}_db_${STAMP}.sql.gz"
fi
STORAGE_TAR="$BACKUP_DIR/${BACKUP_NAME}_storage_${STAMP}.tar.gz"
ENV_BACKUP="$BACKUP_DIR/${BACKUP_NAME}_env_${STAMP}.txt"
[[ -f "$DB_DUMP" ]] || fail "$DB_DUMP nicht gefunden (passt DB_CONNECTION=$DB_CONNECTION zum Backup-Stand?)."

WORK_DIR="${WORK_DIR:-/var/tmp/workdiary-restore-test-${STAMP}}"
[[ ! -e "$WORK_DIR" ]] || fail "$WORK_DIR existiert bereits — anderes --work-dir wählen oder aufräumen."
TEST_DB="wd_restore_${STAMP}"
TEST_DB_USER="wd_restore_test"
TEST_DB_PASS=$(od -vAn -N18 -tx1 /dev/urandom | tr -d ' \n')
TEST_SQLITE="$WORK_DIR/app/database/restore-test.sqlite"

echo "Restore-Test: Instanz $BACKUP_NAME, Stand $STAMP"
note "Backup     = $BACKUP_DIR"
note "Arbeitsverz= $WORK_DIR"
if [[ "$DB_KIND" == "sqlite" ]]; then
  note "Test-DB    = $TEST_SQLITE (SQLite)"
else
  note "Test-DB    = $TEST_DB (User $TEST_DB_USER, temporär)"
fi

# 1) Manifest prüfen (enthält absolute Pfade — Dateien müssen am Ort liegen).
CURRENT_STEP="Manifest-Prüfung"
sha256sum -c --quiet "$MANIFEST" || fail "Manifest-Prüfung fehlgeschlagen — Backup-Dateien beschädigt oder verschoben?"
note "Manifest ok (db/storage/env unversehrt)."

# Ab hier gilt ein Fehlschlag als gescheiterter Restore-Test: Ergebnis wird
# (ohne --no-record) protokolliert, Test-DB + Arbeitsverzeichnis bleiben
# zur Diagnose stehen.
PHASE="restore"
SECONDS=0

record_result() { # $1 result, $2 notes
  [[ $RECORD -eq 1 ]] || return 0
  local args=(workdiary:backup:record-restore-test
    "--source=script-backup:${BACKUP_NAME}" "--result=$1"
    "--scope=DB+Storage+.env, Stand ${STAMP}"
    "--duration-minutes=$(( (SECONDS + 59) / 60 ))" "--notes=$2")
  [[ -n "${RESTORED_BYTES:-}" ]] && args+=("--restored-size-bytes=$RESTORED_BYTES")
  if command -v runuser >/dev/null 2>&1 && [[ "$RUN_USER" != "root" ]]; then
    runuser -u "$RUN_USER" -- "$PHP_BIN" "$APP_DIR/artisan" "${args[@]}" || return 1
  else
    "$PHP_BIN" "$APP_DIR/artisan" "${args[@]}" || return 1
  fi
}

on_exit() {
  local rc=$?
  if [[ $rc -ne 0 && "$PHASE" == "restore" ]]; then
    echo "FEHLER: Restore-Test fehlgeschlagen (Schritt: $CURRENT_STEP)." >&2
    record_result failed "restore-test.sh: fehlgeschlagen bei '$CURRENT_STEP' (Arbeitsverz. $WORK_DIR)" \
      || echo "WARNUNG: Fehlschlag konnte nicht protokolliert werden." >&2
    echo "Zur Diagnose stehen gelassen — Aufräumen danach mit:" >&2
    if [[ "$DB_KIND" == "mysql" ]]; then
      echo "  mysql -e \"DROP DATABASE IF EXISTS \\\`$TEST_DB\\\`; DROP USER IF EXISTS '$TEST_DB_USER'@'localhost', '$TEST_DB_USER'@'127.0.0.1';\"" >&2
    fi
    echo "  rm -rf '$WORK_DIR'" >&2
  fi
}
trap on_exit EXIT

# 2) Codebase kopieren + Storage aus dem Backup entpacken
CURRENT_STEP="Codebase-Kopie"
mkdir -p "$WORK_DIR/app"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude=.git --exclude=node_modules --exclude=storage/app --exclude=.env "$APP_DIR/" "$WORK_DIR/app/"
else
  cp -a "$APP_DIR/." "$WORK_DIR/app/"
  rm -rf "$WORK_DIR/app/.git" "$WORK_DIR/app/node_modules" "$WORK_DIR/app/storage/app" "$WORK_DIR/app/.env"
fi
CURRENT_STEP="Storage entpacken"
# --no-same-owner: Wegwerf-Testumgebung braucht keine Original-Eigentümer —
# und das Entpacken läuft so auch in Containern/User-Namespaces.
tar -C "$WORK_DIR/app" --no-same-owner -xzf "$STORAGE_TAR"
note "Codebase + Storage stehen unter $WORK_DIR/app."

# 3) Test-DB bereitstellen und Dump einspielen
if [[ "$DB_KIND" == "sqlite" ]]; then
  CURRENT_STEP="SQLite-Datei einspielen"
  gunzip < "$DB_DUMP" > "$TEST_SQLITE"
  chmod 600 "$TEST_SQLITE"
else
  CURRENT_STEP="Test-DB anlegen"
  mysql -e "CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  mysql -e "DROP USER IF EXISTS '$TEST_DB_USER'@'localhost', '$TEST_DB_USER'@'127.0.0.1';
            CREATE USER '$TEST_DB_USER'@'localhost' IDENTIFIED BY '$TEST_DB_PASS';
            CREATE USER '$TEST_DB_USER'@'127.0.0.1' IDENTIFIED BY '$TEST_DB_PASS';
            GRANT ALL PRIVILEGES ON \`$TEST_DB\`.* TO '$TEST_DB_USER'@'localhost', '$TEST_DB_USER'@'127.0.0.1';"
  CURRENT_STEP="DB-Dump einspielen"
  gunzip < "$DB_DUMP" | mysql "$TEST_DB"
fi
note "Datenbank eingespielt."

# 4) .env aus dem Backup, auf die Testumgebung umgebogen (APP_KEY bleibt!)
CURRENT_STEP=".env vorbereiten"
TEST_ENV="$WORK_DIR/app/.env"
cp "$ENV_BACKUP" "$TEST_ENV"
chmod 600 "$TEST_ENV"
env_set() { # $1 Key, $2 Wert — Werte hier ohne '|' und Zeilenumbrüche
  if grep -qE "^${1}=" "$TEST_ENV"; then
    sed -i "s|^${1}=.*|${1}=${2}|" "$TEST_ENV"
  else
    printf '%s=%s\n' "$1" "$2" >> "$TEST_ENV"
  fi
}
if [[ "$DB_KIND" == "sqlite" ]]; then
  env_set DB_DATABASE "$TEST_SQLITE"
else
  env_set DB_HOST 127.0.0.1
  env_set DB_PORT 3306
  env_set DB_DATABASE "$TEST_DB"
  env_set DB_USERNAME "$TEST_DB_USER"
  env_set DB_PASSWORD "$TEST_DB_PASS"
fi
env_set APP_URL "http://localhost:8090"
env_set MAIL_MAILER log
env_set QUEUE_CONNECTION sync
env_set BROADCAST_CONNECTION log

# 5) Smoke-Tests in der wiederhergestellten Kopie
artisan_test() { (cd "$WORK_DIR/app" && "$PHP_BIN" artisan "$@"); }
CURRENT_STEP="Caches leeren"
artisan_test optimize:clear --quiet
CURRENT_STEP="Migrationsstand prüfen"
artisan_test migrate:status > /dev/null
CURRENT_STEP="Datenbestand prüfen"
db_count() { # $1 Tabelle
  if [[ "$DB_KIND" == "sqlite" ]]; then
    "$PHP_BIN" -r 'echo (new PDO("sqlite:" . $argv[1]))->query("SELECT COUNT(*) FROM " . $argv[2])->fetchColumn();' "$TEST_SQLITE" "$1"
  else
    mysql -N -e "SELECT COUNT(*) FROM \`$TEST_DB\`.\`$1\`"
  fi
}
USERS=$(db_count users)
MIGRATIONS=$(db_count migrations)
[[ "$USERS" -ge 1 && "$MIGRATIONS" -ge 1 ]] || fail "Datenbestand unplausibel (users=$USERS, migrations=$MIGRATIONS)."
STORAGE_FILES=$(find "$WORK_DIR/app/storage/app" -type f | wc -l)
note "Datenbestand: $USERS User, $MIGRATIONS Migrationen, $STORAGE_FILES Storage-Dateien."

CURRENT_STEP="APP_KEY-Entschlüsselung prüfen"
APP_KEY_CHECK=$(artisan_test tinker --execute='
  $u = \App\Models\User::query()->whereNotNull("two_factor_secret")->first()
    ?? \App\Models\User::query()->whereNotNull("tax_identification_number")->first();
  if ($u === null) { echo "APP_KEY-SKIP"; } else { $u->two_factor_secret ?? $u->tax_identification_number; echo "APP_KEY-OK"; }
' 2>&1 | tail -n1)
case "$APP_KEY_CHECK" in
  APP_KEY-OK)   note "APP_KEY entschlüsselt Bestandsdaten." ;;
  APP_KEY-SKIP) note "APP_KEY-Prüfung übersprungen (keine verschlüsselten Bestandswerte)." ;;
  *) fail "APP_KEY passt nicht zu den Daten: $APP_KEY_CHECK" ;;
esac

# system:health nur informativ — eine ältere Sicherung meldet hier zu Recht
# überfällige Heartbeats/Queues, das widerlegt den Restore nicht.
CURRENT_STEP="system:health (informativ)"
if artisan_test system:health > /dev/null 2>&1; then
  HEALTH_NOTE="system:health ok"
else
  HEALTH_NOTE="system:health mit Befunden (bei Restore-Kopien erwartbar)"
fi
note "$HEALTH_NOTE."

# 6) Kennzahlen + Registereintrag
RESTORED_BYTES=$(( $(du -sb "$WORK_DIR/app/storage/app" | cut -f1) + $(stat -c%s "$DB_DUMP") ))
DURATION_MIN=$(( (SECONDS + 59) / 60 ))
CURRENT_STEP="Registereintrag"
if [[ $RECORD -eq 1 ]]; then
  record_result passed "restore-test.sh: $USERS User, $MIGRATIONS Migrationen, $STORAGE_FILES Storage-Dateien; $APP_KEY_CHECK; $HEALTH_NOTE"
  note "Im Restore-Test-Register protokolliert (source script-backup:${BACKUP_NAME})."
else
  note "Nicht protokolliert (--no-record) — manuell erfassen: Administration → Backup & Restore."
fi

# 7) Aufräumen
PHASE="done"
if [[ $KEEP -eq 1 ]]; then
  echo
  echo "Fertig (bestanden, ${DURATION_MIN} min). Umgebung bleibt (--keep):"
  note "App: $WORK_DIR/app — Probelauf z. B.: (cd $WORK_DIR/app && $PHP_BIN artisan serve --port=8090)"
  if [[ "$DB_KIND" == "mysql" ]]; then
    note "Aufräumen: mysql -e \"DROP DATABASE \\\`$TEST_DB\\\`; DROP USER '$TEST_DB_USER'@'localhost', '$TEST_DB_USER'@'127.0.0.1';\" && rm -rf '$WORK_DIR'"
  else
    note "Aufräumen: rm -rf '$WORK_DIR'"
  fi
else
  if [[ "$DB_KIND" == "mysql" ]]; then
    mysql -e "DROP DATABASE \`$TEST_DB\`; DROP USER '$TEST_DB_USER'@'localhost', '$TEST_DB_USER'@'127.0.0.1';"
  fi
  rm -rf "$WORK_DIR"
  echo
  echo "Fertig: Restore-Test bestanden (${DURATION_MIN} min, $(numfmt --to=iec "$RESTORED_BYTES" 2>/dev/null || echo "$RESTORED_BYTES B")). Testumgebung wieder entfernt."
fi
