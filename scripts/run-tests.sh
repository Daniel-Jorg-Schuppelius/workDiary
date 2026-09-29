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

php artisan config:clear --ansi >/dev/null

export XDEBUG_MODE=off
exec php artisan test --parallel --processes="${TEST_PROCESSES:-8}" "$@"
