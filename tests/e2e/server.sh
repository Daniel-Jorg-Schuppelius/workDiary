#!/usr/bin/env bash
#
# E2E-Testserver (von playwright.config.ts als webServer gestartet).
# Seedet eine frische, eigene SQLite-DB und startet `php artisan serve`.
# Alle ENV-Overrides (DB_DATABASE, LEGACY_*, …) kommen aus der
# webServer.env der Playwright-Config.

set -euo pipefail
cd "$(dirname "$0")/../.."

: "${DB_DATABASE:?DB_DATABASE muss gesetzt sein (playwright.config.ts webServer.env)}"

# shellcheck source=../../scripts/lib/php-bin.sh
source scripts/lib/php-bin.sh
PHP_BIN="$(resolve_php_bin . "${PHP_BIN:-}")"

rm -f "$DB_DATABASE"
touch "$DB_DATABASE"

"$PHP_BIN" artisan migrate:fresh --seed --force --no-interaction

# Default-Org kommt mit Plan "free" aus dem Seeder — das Modul-Gate (423)
# würde sonst fast jede Seite sperren. Für E2E: alles freischalten.
"$PHP_BIN" artisan tinker --execute='\App\Models\Platform\Organization::query()->update(["plan" => "enterprise"]);'

# Hilfecenter (MVP-752): help_topics wird im Deploy per Reindex befüllt —
# die frische E2E-DB braucht denselben Schritt, sonst ist /hilfe leer.
"$PHP_BIN" artisan help:reindex

exec "$PHP_BIN" artisan serve --host=127.0.0.1 --port="${E2E_PORT:-8010}"
