#!/usr/bin/env bash
#
# WorkDiary Testlauf mit Env-Isolation.
#
# Manche Shells exportieren die lokale `.env` (z. B. `set -a; source .env`, oder
# ein Terminal, das die Projekt-Umgebung vorlädt). Dann überschreiben
# `APP_ENV=local` / `DB_CONNECTION=sqlite` … die `<env>`-Werte aus `phpunit.xml`,
# weil `php artisan` die `.env` zuerst lädt und Laravel bereits gesetzte
# Prozess-Variablen bevorzugt (die `<env>`-Vorgaben ohne `force` greifen dann
# nicht). Folge: Tests laufen gegen die Dev-DB (`development.sqlite`,
# „database is locked" im Parallellauf) UND `runningUnitTests()` ist false →
# der CSRF-Schutz wird nicht übersprungen → massenhaft 419.
#
# Darum entfernen wir vor dem Lauf alle in `.env` definierten Schlüssel aus der
# Umgebung, sodass ausschließlich `phpunit.xml` (+ `.env.testing`) den
# Testkontext bestimmt. Ohne `.env` (CI) ist das ein No-op.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f .env ]; then
    keys="$(sed -E 's/^[[:space:]]*(export[[:space:]]+)?//; s/[[:space:]=].*//; /^#/d; /^$/d' .env | tr '\n' ' ')"
    if [ -n "$keys" ]; then
        # shellcheck disable=SC2086
        unset $keys || true
    fi
fi

# shellcheck source=lib/php-bin.sh
source scripts/lib/php-bin.sh
PHP_BIN="$(resolve_php_bin . "${PHP_BIN:-}")"

"$PHP_BIN" artisan config:clear --ansi >/dev/null

export XDEBUG_MODE=off
# OPcache auch auf der Kommandozeile (tests/php.d/opcache.ini): ohne ihn
# kompiliert jeder Test Routen, Konfiguration und Views neu, und ein Worker
# behielt je Test rund 0,3 MB, bis das memory_limit (1G) riss. Ein leerer
# Eintrag vor dem Doppelpunkt steht für die Scan-Verzeichnisse der
# Installation; die Paratest-Worker erben die Variable.
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$PWD/tests/php.d"
# Der Neustart nach N Testdateien bleibt als Absicherung, falls OPcache fehlt.
exec "$PHP_BIN" artisan test --parallel --processes="${TEST_PROCESSES:-8}" --max-batch-size="${TEST_MAX_BATCH:-100}" "$@"
