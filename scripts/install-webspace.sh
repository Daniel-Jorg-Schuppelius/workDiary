#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

APP_URL=""
CHOWN=""
SKIP_COMPOSER=0
SKIP_ASSETS=0
SKIP_MIGRATIONS=0
SKIP_CACHE=0

usage() {
    cat <<'USAGE'
WorkDiary Webspace-Installation

Usage:
  bash scripts/install-webspace.sh --url=https://example.com

Options:
  --url=URL           Setzt APP_URL in .env.
  --chown=USER:GROUP  Setzt Eigentuemer von storage/ und bootstrap/cache (braucht
                      root; behebt root-eigene Dateien aus versehentlichen
                      root-Laeufen, die tempnam-/Permission-Fehler ausloesen).
  --skip-composer    Ueberspringt composer install.
  --skip-assets      Ueberspringt npm ci/install und npm run build.
  --skip-migrations  Ueberspringt php artisan migrate --force.
  --skip-cache       Ueberspringt Laravel Production-Caches.
  -h, --help         Zeigt diese Hilfe.

Vor dem Start:
  - Webroot/Dokumentenstamm des Hosters muss auf public/ zeigen.
  - Datenbank-Zugangsdaten muessen in .env gepflegt sein.
  - PHP CLI, Composer und fuer Asset-Builds Node.js/npm muessen verfuegbar sein.
USAGE
}

for arg in "$@"; do
    case "$arg" in
        --url=*)
            APP_URL="${arg#*=}"
            ;;
        --chown=*)
            CHOWN="${arg#*=}"
            ;;
        --skip-composer)
            SKIP_COMPOSER=1
            ;;
        --skip-assets)
            SKIP_ASSETS=1
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=1
            ;;
        --skip-cache)
            SKIP_CACHE=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unbekannte Option: $arg" >&2
            usage >&2
            exit 1
            ;;
    esac
done

log() {
    printf '\n==> %s\n' "$1"
}

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Fehlender Befehl: $1" >&2
        exit 1
    fi
}

require_php_version() {
    local min_major=8
    local min_minor=4
    local current
    current="$(php -r 'echo PHP_VERSION;')"

    if ! php -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);'; then
        echo "PHP ${min_major}.${min_minor} oder neuer wird benoetigt, gefunden: ${current}." >&2
        echo "Bitte auf dem Webspace ein PHP-${min_major}.${min_minor}-CLI auswaehlen (z. B. ueber das Hosting-Panel)." >&2
        exit 1
    fi
}

preflight_webserver() {
    # Best-effort: laeuft das Skript unprivilegiert oder unter nginx, sind das nur
    # Hinweise (kein Abbruch). Die harten Schreibrechte-Checks kommen spaeter.
    if command -v apachectl >/dev/null 2>&1; then
        if ! apachectl -M 2>/dev/null | grep -qi 'rewrite_module'; then
            echo "WARNUNG: Apache mod_rewrite ist nicht geladen — der Webserver liefert dann" >&2
            echo "         statische Dateien statt der App. Fix: a2enmod rewrite && systemctl restart apache2" >&2
        fi
    fi
    echo "Hinweis: Docroot der Domain MUSS auf public/ zeigen und AllowOverride All aktiv sein,"
    echo "         sonst ist u. a. die .env web-erreichbar (siehe docs/systemdienste.md)."
}

env_get() {
    local key="$1"
    local line

    if [[ ! -f .env ]]; then
        return 0
    fi

    line="$(grep -E "^${key}=" .env | tail -n 1 || true)"
    printf '%s' "${line#*=}" | sed -e 's/^"//' -e 's/"$//'
}

env_set() {
    local key="$1"
    local value="$2"
    local escaped

    escaped="$(printf '%s' "$value" | sed -e 's/[\/&]/\\&/g')"

    if grep -qE "^${key}=" .env; then
        sed -i.bak "s/^${key}=.*/${key}=${escaped}/" .env
        rm -f .env.bak
    else
        printf '%s=%s\n' "$key" "$value" >> .env
    fi
}

log "Pruefe Umgebung"
require_command php
require_php_version
preflight_webserver

if [[ "$SKIP_COMPOSER" -eq 0 ]]; then
    require_command composer
fi

if [[ "$SKIP_ASSETS" -eq 0 ]]; then
    require_command npm
fi

if [[ ! -f .env ]]; then
    log "Erzeuge .env aus .env.example"
    cp .env.example .env
fi

log "Setze Production-Defaults"
env_set APP_ENV production
env_set APP_DEBUG false
env_set SESSION_SECURE_COOKIE true
env_set SESSION_HTTP_ONLY true
env_set SESSION_SAME_SITE lax
env_set SESSION_ENCRYPT true

if [[ -n "$APP_URL" ]]; then
    env_set APP_URL "$APP_URL"
fi

if [[ "$(env_get DB_CONNECTION)" == "sqlite" ]]; then
    mkdir -p database
    touch database/database.sqlite
fi

if [[ "$SKIP_COMPOSER" -eq 0 ]]; then
    log "Installiere PHP-Abhaengigkeiten"
    # Composer-Cache in die Site legen: ISPConfig-Home (.../webNNN) ist immutable.
    export COMPOSER_CACHE_DIR="$PWD/storage/framework/cache/composer"
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
fi

if [[ -z "$(env_get APP_KEY)" ]]; then
    log "Erzeuge APP_KEY"
    php artisan key:generate --force
fi

if [[ "$SKIP_ASSETS" -eq 0 ]]; then
    log "Baue Frontend-Assets"
    if [[ -f package-lock.json ]]; then
        npm ci --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi

    npm run build
fi

log "Bereite beschreibbare Verzeichnisse vor"
mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
# Stale kompilierte Views/Caches entfernen (koennen einem anderen Benutzer gehoeren
# und sonst tempnam-/Permission-Fehler beim Rendern ausloesen).
find storage/framework/views -maxdepth 1 -type f -name '*.php' -delete 2>/dev/null || true
find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete 2>/dev/null || true
if [[ -n "$CHOWN" ]]; then
    log "Setze Eigentuemer der Schreibpfade auf $CHOWN"
    # storage/ + bootstrap/cache immer; .env (der Wizard schreibt DB/APP_KEY) und
    # database/ (SQLite) nur wenn vorhanden — alle vier beschreibt die App/der Wizard
    # zur Laufzeit. Ohne das scheitert der Web-Installer an einer root-eigenen .env.
    _chown_targets=(storage bootstrap/cache)
    [[ -e .env ]] && _chown_targets+=(.env)
    [[ -d database ]] && _chown_targets+=(database)
    if ! chown -R "$CHOWN" "${_chown_targets[@]}" 2>/dev/null; then
        echo "FEHLER: chown auf '$CHOWN' fehlgeschlagen — als root ausfuehren (sudo) oder gueltige USER:GROUP angeben." >&2
        exit 1
    fi
fi
chmod -R ug+rwX storage bootstrap/cache
# Verifizieren, dass die kritischen Pfade wirklich beschreibbar sind — sonst
# scheitert Blade beim Kompilieren (tempnam) mit HTTP 500 statt Installer.
for _wdir in storage/framework/views bootstrap/cache; do
    if ! ( : > "$_wdir/.wtest.$$" ) 2>/dev/null; then
        echo "FEHLER: $_wdir ist nicht beschreibbar (aktueller User: $(id -un))." >&2
        echo "        Als Web-User ausfuehren oder Eigentuemer setzen:" >&2
        echo "        sudo bash scripts/install-webspace.sh --chown=WEBUSER:GRUPPE …" >&2
        exit 1
    fi
    rm -f "$_wdir/.wtest.$$"
done

log "Erzeuge Storage-Link"
if [[ -L public/storage || -e public/storage ]]; then
    echo "  vorhanden, uebersprungen"
else
    php artisan storage:link || true
fi

if [[ "$SKIP_MIGRATIONS" -eq 0 ]]; then
    log "Fuehre Datenbank-Migrationen aus"
    php artisan migrate --force
fi

if [[ "$SKIP_CACHE" -eq 0 ]]; then
    log "Erzeuge Production-Caches"
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

log "Starte Queue-Worker neu, falls vorhanden"
if [[ "$SKIP_MIGRATIONS" -eq 0 ]]; then
    php artisan queue:restart || true
else
    echo "  uebersprungen (Migrationen ausgelassen — DB/cache-Tabelle evtl. noch nicht vorhanden)"
fi

cat <<'DONE'

Installation abgeschlossen.

Naechste Schritte:
  1. Domain/Webroot im Hosting-Panel auf public/ setzen.
  2. .env pruefen: APP_URL, DB_*, MAIL_*, optionale Integrationen.
  3. Cron fuer Laravel Scheduler einrichten:
     * * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
  4. Queue-Worker dauerhaft ueber Supervisor, systemd oder Hoster-Job starten:
     php artisan queue:work --tries=3

Hinweis: php artisan view:cache wird bewusst nicht ausgefuehrt.
DONE
