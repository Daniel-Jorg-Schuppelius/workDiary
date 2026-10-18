#!/usr/bin/env bash
#
# WorkDiary – Composer mit dem passenden PHP-Binary.
#
# `composer` trägt den Shebang `#!/usr/bin/env php` und liefe mit der
# CLI-Vorgabe des Servers; ist die älter als require.php der composer.json,
# bricht Composers Plattform-Check jeden Aufruf ab. Dieses Skript sucht das PHP
# wie scripts/artisan.sh (scripts/lib/php-bin.sh) und reicht alle Argumente
# an Composer durch:
#   /pfad/zur/app/scripts/composer.sh install --no-dev --optimize-autoloader
#
# Als root gestartet, läuft Composer als Eigentümer von storage/ (wie
# artisan.sh): Sonst gehörten vendor/ und die Autoload-Maps root, und der
# nächste Deploy könnte sie nicht ersetzen.
#
# Der Composer-Cache liegt wie in deploy.sh unter storage/framework/cache/
# composer: das Home des Web-Users ist auf ISPConfig-Servern immutable.
#
# Optionale Env-Variablen:
#   APP_DIR            – WorkDiary-Installationsverzeichnis (default: Verzeichnis dieses Scripts/..)
#   PHP_BIN            – PHP-Interpreter (default: passend zur composer.json gesucht)
#   RUN_USER           – Benutzer bei Start als root (default: Eigentümer von storage/)
#   COMPOSER_CACHE_DIR – Cache-Verzeichnis (default: storage/framework/cache/composer)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="${APP_DIR:-$(cd "$SCRIPT_DIR/.." && pwd)}"
# shellcheck source=lib/php-bin.sh
source "$SCRIPT_DIR/lib/php-bin.sh"
PHP_BIN="$(resolve_php_bin "$APP_DIR" "${PHP_BIN:-}")"

cd "$APP_DIR"
export COMPOSER_CACHE_DIR="${COMPOSER_CACHE_DIR:-$PWD/storage/framework/cache/composer}"
composer_cmd

if [ "$(id -u)" -eq 0 ]; then
    RUN_USER="${RUN_USER:-$(stat -c %U storage 2>/dev/null || echo root)}"
    if [ "$RUN_USER" != "root" ]; then
        exec runuser -u "$RUN_USER" -- "${COMPOSER_CMD[@]}" "$@"
    fi
fi

exec "${COMPOSER_CMD[@]}" "$@"
