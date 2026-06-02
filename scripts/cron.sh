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

cd "$APP_DIR"

# 1) Scheduler – entscheidet selbst, welche Aufgaben fällig sind.
"$PHP_BIN" artisan schedule:run

# 2) Queue – kurzer Durchlauf, beendet sich selbst, wenn die Queue leer ist
#    oder QUEUE_MAX_TIME erreicht ist. Fehler hier dürfen den Cron-Lauf nicht
#    abbrechen (z. B. wenn QUEUE_CONNECTION=sync ist).
if [ "$RUN_QUEUE" = "1" ]; then
    "$PHP_BIN" artisan queue:work --stop-when-empty --max-time="$QUEUE_MAX_TIME" || true
fi
