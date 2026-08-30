#!/usr/bin/env bash
#
# WorkDiary – kombiniertes Cron-Script.
#
# Führt sowohl den Laravel-Scheduler als auch (optional) einen kurzen
# Queue-Durchlauf aus, sodass nur EIN Eintrag im Cron hinterlegt werden muss.
#
# Cron-Beispiel (jede Minute):
#   * * * * * /var/www/scripts/cron.sh >> /dev/null 2>&1
#
# Konfiguration über optionale Env-Variablen (sonst sinnvolle Defaults):
#   APP_DIR        – WorkDiary-Installationsverzeichnis (default: Verzeichnis dieses Scripts/..)
#   PHP_BIN        – PHP-Interpreter (default: php)
#   RUN_QUEUE      – "1" = Queue mit abarbeiten, "0" = überspringen (default: 1)
#   QUEUE_MAX_TIME – Sekunden, die der Queue-Worker pro Lauf arbeitet (default: 55)
#   RUN_MEDIA_QUEUE – "1" = Medien-Warteschlange mit abarbeiten (default: 1)
#   MEDIA_TIMEOUT  – Sekunden, die ein einzelner Medien-Job laufen darf (default: 3600)
#
# Hinweis: Bei QUEUE_CONNECTION=sync kann RUN_QUEUE=0 gesetzt werden.

set -euo pipefail

# Installationsverzeichnis bestimmen (eine Ebene über diesem Script),
# sofern nicht explizit per APP_DIR vorgegeben.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
RUN_QUEUE="${RUN_QUEUE:-1}"
QUEUE_MAX_TIME="${QUEUE_MAX_TIME:-55}"
RUN_MEDIA_QUEUE="${RUN_MEDIA_QUEUE:-1}"
MEDIA_TIMEOUT="${MEDIA_TIMEOUT:-3600}"

cd "$APP_DIR"

# 1) Scheduler – entscheidet selbst, welche Aufgaben fällig sind.
"$PHP_BIN" artisan schedule:run

# 2) Queue – kurzer Durchlauf, beendet sich selbst, wenn die Queue leer ist
#    oder QUEUE_MAX_TIME erreicht ist. Fehler hier dürfen den Cron-Lauf nicht
#    abbrechen (z. B. wenn QUEUE_CONNECTION=sync ist).
if [ "$RUN_QUEUE" = "1" ]; then
    # --tries/--backoff: ohne sie zählt jeder transiente Fehler als endgültig
    # (Default tries=1) — Jobs mit eigener $tries-/backoff()-Angabe behalten
    # ihre Werte, die Optionen greifen nur als Untergrenze (Vollscan 2026-08-23, J7).
    "$PHP_BIN" artisan queue:work --stop-when-empty --max-time="$QUEUE_MAX_TIME" --tries=3 --backoff=30 || true
fi

# 3) Medien-Warteschlange (Feature 150) – Transcoding und Untertitel.
#    Eigener Durchlauf, weil ein Video minutenlang rechnet: im 55-Sekunden-
#    Fenster des Standard-Workers käme es nie zu Ende. Kein --max-time, dafür
#    --stop-when-empty; der Sperrdatei-Wächter verhindert, dass die Minute
#    darauf ein zweiter Worker denselben Job anfasst. Ohne flock läuft der
#    Durchgang trotzdem – dann nur ohne diesen Schutz.
if [ "$RUN_MEDIA_QUEUE" = "1" ]; then
    MEDIA_LOCK="$APP_DIR/storage/framework/media-queue.lock"
    if command -v flock >/dev/null 2>&1; then
        flock -n "$MEDIA_LOCK" \
            "$PHP_BIN" artisan queue:work media --queue=media --stop-when-empty \
            --timeout="$MEDIA_TIMEOUT" --tries=1 || true
    else
        "$PHP_BIN" artisan queue:work media --queue=media --stop-when-empty \
            --timeout="$MEDIA_TIMEOUT" --tries=1 || true
    fi
fi
