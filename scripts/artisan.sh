#!/usr/bin/env bash
#
# WorkDiary – artisan mit dem passenden PHP-Binary.
#
# Sucht das PHP wie cron.sh und deploy.sh (scripts/lib/php-bin.sh) und reicht
# alle Argumente an artisan durch:
#   /pfad/zur/app/scripts/artisan.sh lexoffice:sync-voucher-lines --all --refresh
#
# Als root gestartet, läuft artisan als Eigentümer von storage/ (wie in
# install-system.sh): Sonst gehören neue Log- und Cache-Dateien root, und das
# Web kann sie nicht mehr schreiben.
#
# Optionale Env-Variablen:
#   APP_DIR  – WorkDiary-Installationsverzeichnis (default: Verzeichnis dieses Scripts/..)
#   PHP_BIN  – PHP-Interpreter (default: passend zur composer.json gesucht)
#   RUN_USER – Benutzer bei Start als root (default: Eigentümer von storage/)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
# shellcheck source=lib/php-bin.sh
source "$SCRIPT_DIR/lib/php-bin.sh"
PHP_BIN="$(resolve_php_bin "$APP_DIR" "${PHP_BIN:-}")"

cd "$APP_DIR"

if [ "$(id -u)" -eq 0 ]; then
    RUN_USER="${RUN_USER:-$(stat -c %U storage 2>/dev/null || echo root)}"
    if [ "$RUN_USER" != "root" ]; then
        exec runuser -u "$RUN_USER" -- "$PHP_BIN" artisan "$@"
    fi
fi

exec "$PHP_BIN" artisan "$@"
