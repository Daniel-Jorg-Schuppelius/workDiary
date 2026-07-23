#!/usr/bin/env bash
#
# WorkDiary Systemdienst-Installer (MVP-454).
#
# Richtet nach der Web-Installation alles ein, was die App für den vollen
# Betrieb braucht — dynamisch aus der Installation ermittelt (APP_DIR aus dem
# Skriptpfad, PHP-Binary, Betriebs-User aus dem storage/-Owner):
#
#   immer:              /etc/cron.d/workdiary        (schedule:run minütlich + Backup)
#                       /etc/workdiary-backup.conf   (Backup-Ziel/Retention, chmod 600)
#                       workdiary-queue.service      (QUEUE_CONNECTION=database!)
#   --with-reverb:      workdiary-reverb.service     (WebSocket/Chat)
#   --with-integrity-watch: workdiary-integrity-watch.service (braucht ext-inotify)
#   --with-fail2ban:    Filter + Jail aus deploy/fail2ban (logpath eingesetzt)
#
# Aufruf (als root):
#   scripts/install-system.sh [--with-reverb] [--with-integrity-watch]
#                             [--with-fail2ban] [--backup-time HH:MM]
#                             [--backup-dir PFAD] [--backup-keep-days N]
#                             [--no-backup] [--dry-run] [--status] [--uninstall]
#
# Idempotent: erneutes Ausführen überschreibt die erzeugten Dateien und lädt
# die Dienste neu — nur /etc/workdiary-backup.conf bleibt unangetastet, außer
# eine --backup-dir/--backup-keep-days-Option wird explizit übergeben.
# --uninstall entfernt alles wieder. DESTDIR (Paketbau/Test) schreibt unter
# ein Präfix und überspringt systemctl/fail2ban-Aufrufe.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(dirname "$SCRIPT_DIR")}"
DESTDIR="${DESTDIR:-}"

WITH_REVERB=0
WITH_WATCH=0
WITH_FAIL2BAN=0
WITH_BACKUP=1
BACKUP_TIME="23:00"
BACKUP_DIR="/var/backups/workdiary"
BACKUP_KEEP_DAYS=14
BACKUP_CONF_OPTS=0
DRY_RUN=0
ACTION="install"

usage() { grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --with-reverb) WITH_REVERB=1 ;;
    --with-integrity-watch) WITH_WATCH=1 ;;
    --with-fail2ban) WITH_FAIL2BAN=1 ;;
    --no-backup) WITH_BACKUP=0 ;;
    --backup-time) shift; BACKUP_TIME="${1:?--backup-time braucht HH:MM}" ;;
    --backup-dir) shift; BACKUP_DIR="${1:?--backup-dir braucht einen Pfad}"; BACKUP_CONF_OPTS=1 ;;
    --backup-keep-days) shift; BACKUP_KEEP_DAYS="${1:?--backup-keep-days braucht eine Zahl}"; BACKUP_CONF_OPTS=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --status) ACTION="status" ;;
    --uninstall) ACTION="uninstall" ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unbekannte Option: $1" >&2; usage >&2; exit 64 ;;
  esac
  shift
done

# ---------------------------------------------------------------- Erkennung

fail() { echo "FEHLER: $*" >&2; exit 1; }
note() { echo "  $*"; }

[[ -f "$APP_DIR/artisan" ]] || fail "$APP_DIR/artisan nicht gefunden — Skript aus <app>/scripts/ ausführen oder APP_DIR setzen."
if [[ -z "$DESTDIR" && $DRY_RUN -eq 0 && $EUID -ne 0 ]]; then
  fail "bitte als root ausführen (schreibt nach /etc und steuert systemd)."
fi

# PHP-Binary: Override → PATH → gängige versionsierte Pfade.
detect_php() {
  if [[ -n "${PHP_BIN:-}" ]]; then printf '%s' "$PHP_BIN"; return; fi
  local candidate
  for candidate in php php8.4 /usr/bin/php /usr/bin/php8.4; do
    if command -v "$candidate" >/dev/null 2>&1; then
      command -v "$candidate"; return
    fi
  done
  fail "kein PHP-Binary gefunden — PHP_BIN=/pfad/zu/php setzen."
}
PHP_BIN="$(detect_php)"

# Betriebs-User: Owner von storage/ (dem gehören Laravel-Schreibpfade).
RUN_USER="${RUN_USER:-$(stat -c %U "$APP_DIR/storage" 2>/dev/null || echo www-data)}"
[[ "$RUN_USER" != "root" ]] || note "WARNUNG: storage/ gehört root — RUN_USER=root ist unüblich (RUN_USER=... übersteuern?)."

# Backup-Zeit HH:MM → Cron-Felder.
[[ "$BACKUP_TIME" =~ ^([01]?[0-9]|2[0-3]):([0-5][0-9])$ ]] || fail "--backup-time erwartet HH:MM (bekam: $BACKUP_TIME)."
BACKUP_HOUR="${BACKUP_TIME%%:*}"; BACKUP_HOUR=$((10#$BACKUP_HOUR))
BACKUP_MIN="${BACKUP_TIME##*:}";  BACKUP_MIN=$((10#$BACKUP_MIN))

[[ "$BACKUP_DIR" == /* ]] || fail "--backup-dir erwartet einen absoluten Pfad (bekam: $BACKUP_DIR)."
[[ "$BACKUP_KEEP_DAYS" =~ ^[1-9][0-9]*$ ]] || fail "--backup-keep-days erwartet eine positive Zahl (bekam: $BACKUP_KEEP_DAYS)."

SYSTEMD_DIR="$DESTDIR/etc/systemd/system"
CRON_FILE="$DESTDIR/etc/cron.d/workdiary"
BACKUP_CONF="$DESTDIR/etc/workdiary-backup.conf"
F2B_DIR="$DESTDIR/etc/fail2ban"

UNITS=(workdiary-queue)
[[ $WITH_REVERB -eq 1 ]] && UNITS+=(workdiary-reverb)
[[ $WITH_WATCH -eq 1 ]] && UNITS+=(workdiary-integrity-watch)

sysctl_do() { # systemctl nur im Echtbetrieb (kein DESTDIR/dry-run)
  if [[ -n "$DESTDIR" || $DRY_RUN -eq 1 ]]; then note "(übersprungen) systemctl $*"; else systemctl "$@"; fi
}

render() { # $1 Template, $2 Ziel
  local content
  content=$(sed -e "s|__APP_DIR__|$APP_DIR|g" \
                -e "s|__PHP_BIN__|$PHP_BIN|g" \
                -e "s|__RUN_USER__|$RUN_USER|g" \
                -e "s|__BACKUP_MIN__|$BACKUP_MIN|g" \
                -e "s|__BACKUP_HOUR__|$BACKUP_HOUR|g" "$1")
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "--- würde schreiben: $2"
    echo "$content" | sed 's/^/    /'
  else
    mkdir -p "$(dirname "$2")"
    printf '%s\n' "$content" > "$2"
    chmod 644 "$2"
    note "geschrieben: $2"
  fi
}

# Backup-Konfiguration: chmod 600 (darf den Heartbeat-Token aufnehmen), daher
# nicht über render(). Bestehende Datei bleibt erhalten, außer --backup-*
# wurde explizit übergeben.
write_backup_conf() {
  if [[ -f "$BACKUP_CONF" && $BACKUP_CONF_OPTS -eq 0 ]]; then
    note "vorhanden, unverändert: $BACKUP_CONF"
    return
  fi
  local content
  content="# WorkDiary-Backup-Konfiguration — wird von scripts/backup.sh gelesen.
# Erzeugt von scripts/install-system.sh; Änderungen hier überleben erneute
# Installer-Läufe (nur explizite --backup-*-Optionen schreiben die Datei neu).
BACKUP_DIR=\"$BACKUP_DIR\"
BACKUP_KEEP_DAYS=$BACKUP_KEEP_DAYS
# BACKUP_NAME=\"meine-instanz\"                              # Instanzname in den Dateinamen; Default: APP_NAME aus der App-.env
# BACKUP_HEARTBEAT_URL=\"https://…/admin/backup/heartbeat\"  # Default: <APP_URL>/admin/backup/heartbeat aus der App-.env
# BACKUP_HEARTBEAT_TOKEN=\"…\"                               # Default: BACKUP_HEARTBEAT_TOKEN aus der App-.env"
  if [[ $DRY_RUN -eq 1 ]]; then
    echo "--- würde schreiben: $BACKUP_CONF"
    echo "$content" | sed 's/^/    /'
  else
    mkdir -p "$(dirname "$BACKUP_CONF")"
    printf '%s\n' "$content" > "$BACKUP_CONF"
    chmod 600 "$BACKUP_CONF"
    note "geschrieben: $BACKUP_CONF (chmod 600)"
  fi
}

# ---------------------------------------------------------------- Aktionen

status() {
  echo "WorkDiary-Systemdienste (APP_DIR=$APP_DIR):"
  for u in workdiary-queue workdiary-reverb workdiary-integrity-watch; do
    if [[ -f "/etc/systemd/system/$u.service" ]]; then
      printf '  %-28s %s / %s\n' "$u" "$(systemctl is-enabled "$u" 2>/dev/null || true)" "$(systemctl is-active "$u" 2>/dev/null || true)"
    else
      printf '  %-28s nicht installiert\n' "$u"
    fi
  done
  [[ -f /etc/cron.d/workdiary ]] && echo "  /etc/cron.d/workdiary        vorhanden" || echo "  /etc/cron.d/workdiary        nicht installiert"
  [[ -f /etc/workdiary-backup.conf ]] && echo "  /etc/workdiary-backup.conf   vorhanden" || echo "  /etc/workdiary-backup.conf   nicht installiert (backup.sh nutzt Defaults)"
  [[ -f /etc/fail2ban/jail.d/workdiary.conf ]] && echo "  fail2ban-Jail                vorhanden" || echo "  fail2ban-Jail                nicht installiert"
}

uninstall() {
  echo "Entferne WorkDiary-Systemdienste …"
  for u in workdiary-queue workdiary-reverb workdiary-integrity-watch; do
    if [[ -f "$SYSTEMD_DIR/$u.service" ]]; then
      sysctl_do disable --now "$u" || true
      rm -f "$SYSTEMD_DIR/$u.service"; note "entfernt: $SYSTEMD_DIR/$u.service"
    fi
  done
  sysctl_do daemon-reload || true
  [[ -f "$CRON_FILE" ]] && { rm -f "$CRON_FILE"; note "entfernt: $CRON_FILE"; }
  [[ -f "$BACKUP_CONF" ]] && { rm -f "$BACKUP_CONF"; note "entfernt: $BACKUP_CONF"; }
  if [[ -f "$F2B_DIR/jail.d/workdiary.conf" ]]; then
    rm -f "$F2B_DIR/jail.d/workdiary.conf" "$F2B_DIR/filter.d/workdiary.conf" "$F2B_DIR/filter.d/workdiary-strict.conf"
    note "entfernt: fail2ban-Filter/-Jail"
    if [[ -z "$DESTDIR" ]] && command -v fail2ban-client >/dev/null 2>&1; then fail2ban-client reload >/dev/null || true; fi
  fi
  echo "Fertig. (Die App selbst und /var/backups bleiben unangetastet.)"
}

install() {
  echo "WorkDiary-Systemdienste einrichten:"
  note "APP_DIR  = $APP_DIR"
  note "PHP      = $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo '?'))"
  note "RUN_USER = $RUN_USER"

  # Wächter nur mit ext-inotify (sonst Restart-Schleife im Unit).
  if [[ $WITH_WATCH -eq 1 ]] && ! "$PHP_BIN" -m 2>/dev/null | grep -qx inotify; then
    fail "--with-integrity-watch braucht ext-inotify ($PHP_BIN meldet sie nicht). Ubuntu/Debian: apt install php8.4-inotify"
  fi

  # 1) Cron (Herzschlag + optional Backup) + Backup-Konfiguration
  if [[ $WITH_BACKUP -eq 1 ]]; then
    render "$APP_DIR/deploy/cron.d/workdiary.template" "$CRON_FILE"
    write_backup_conf
  else
    render <(grep -v 'backup.sh' "$APP_DIR/deploy/cron.d/workdiary.template") "$CRON_FILE"
  fi

  # 2) systemd-Units
  for u in "${UNITS[@]}"; do
    render "$APP_DIR/deploy/systemd/$u.service.template" "$SYSTEMD_DIR/$u.service"
  done
  sysctl_do daemon-reload
  for u in "${UNITS[@]}"; do
    sysctl_do enable --now "$u"
    sysctl_do restart "$u"   # Idempotenz: bei erneutem Lauf neue Unit-Datei übernehmen
  done

  # 3) fail2ban (optional)
  if [[ $WITH_FAIL2BAN -eq 1 ]]; then
    if [[ -z "$DESTDIR" ]] && ! command -v fail2ban-client >/dev/null 2>&1; then
      fail "--with-fail2ban: fail2ban ist nicht installiert (apt install fail2ban)."
    fi
    if [[ $DRY_RUN -eq 1 ]]; then
      note "(dry-run) würde fail2ban-Filter/-Jail nach $F2B_DIR kopieren"
    else
      mkdir -p "$F2B_DIR/filter.d" "$F2B_DIR/jail.d"
      cp "$APP_DIR/deploy/fail2ban/filter.d/workdiary.conf" \
         "$APP_DIR/deploy/fail2ban/filter.d/workdiary-strict.conf" "$F2B_DIR/filter.d/"
      sed "s|/var/www/workdiary|$APP_DIR|g; s|logpath  = .*storage/logs|logpath  = $APP_DIR/storage/logs|g" \
        "$APP_DIR/deploy/fail2ban/jail.d/workdiary.conf.example" > "$F2B_DIR/jail.d/workdiary.conf"
      note "geschrieben: $F2B_DIR/jail.d/workdiary.conf (+ Filter)"
      if [[ -z "$DESTDIR" ]]; then fail2ban-client reload >/dev/null && note "fail2ban neu geladen."; fi
    fi
  fi

  echo
  echo "Fertig. Kontrolle:"
  note "systemctl status ${UNITS[*]}"
  note "$PHP_BIN $APP_DIR/artisan schedule:list   # Scheduler-Herzschlag prüfen"
  [[ $WITH_BACKUP -eq 1 ]] && note "Backup täglich ${BACKUP_TIME} Uhr → /var/log/workdiary-backup.log (Ziel/Retention: /etc/workdiary-backup.conf; Zeit muss in der Server-Betriebszeit liegen!)"
  note "Heartbeat-Token (Backup-Statusseite): $PHP_BIN $APP_DIR/artisan workdiary:backup:rotate-token"
}

case "$ACTION" in
  status) status ;;
  uninstall) uninstall ;;
  install) install ;;
esac
