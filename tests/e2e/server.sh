#!/usr/bin/env bash
#
# E2E-Testserver (von playwright.config.ts als webServer gestartet).
# Seedet eine frische, eigene SQLite-DB und startet `php artisan serve`.
# Alle ENV-Overrides (DB_DATABASE, LEGACY_*, …) kommen aus der
# webServer.env der Playwright-Config.

set -euo pipefail
cd "$(dirname "$0")/../.."

: "${DB_DATABASE:?DB_DATABASE muss gesetzt sein (playwright.config.ts webServer.env)}"

rm -f "$DB_DATABASE"
touch "$DB_DATABASE"

php artisan migrate:fresh --seed --force --no-interaction

# Default-Org kommt mit Plan "free" aus dem Seeder — das Modul-Gate (423)
# würde sonst fast jede Seite sperren. Für E2E: alles freischalten.
php artisan tinker --execute='\App\Models\Organization::query()->update(["plan" => "enterprise"]);'

exec php artisan serve --host=127.0.0.1 --port="${E2E_PORT:-8010}"
