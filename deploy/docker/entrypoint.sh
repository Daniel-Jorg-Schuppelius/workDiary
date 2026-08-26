#!/usr/bin/env bash
#
# WorkDiary Container-Entrypoint (MVP-720). Läuft als www-data.
#
# Ablauf für Dauerläufer (php-fpm, queue:work, schedule:work, reverb:start):
#   1. APP_KEY-Pflicht (ohne Schlüssel keine verschlüsselten Felder → Abbruch)
#   2. storage-/bootstrap-cache-Skelett (Volumes können leer starten)
#   3. auf die Datenbank warten (WD_DB_WAIT_SECONDS, Default 60)
#   4. WD_AUTO_MIGRATE=1 → migrate --force + PermissionsSeeder + help:reindex
#      (nur der Web-Dienst setzt das Flag; bewusst KEIN db:seed der
#      org-editierbaren Kataloge — Vollscan J3, die Kataloge legt der
#      OrganizationObserver je neuer Org an)
#   5. package:discover + config/route/event-Cache (kein view:cache — Projektregel)
#   6. exec des eigentlichen Kommandos
#
# Einmal-Kommandos (docker run … php artisan --version, app:admin, …)
# überspringen 1/3/4/5 und laufen direkt gegen die ENV.

set -euo pipefail
cd /var/www/html

role="oneoff"
case "${1:-}" in
    php-fpm) role="web" ;;
    php|artisan)
        case "$*" in
            *queue:work*|*queue:listen*) role="queue" ;;
            *schedule:work*|*schedule:run*) role="scheduler" ;;
            *reverb:start*) role="reverb" ;;
            *integrity:watch*) role="watcher" ;;
        esac
        ;;
esac

log() { printf '[wd-entrypoint] %s\n' "$*" >&2; }

ensure_skeleton() {
    mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/framework/testing \
             storage/logs bootstrap/cache
    if [[ "${DB_CONNECTION:-sqlite}" == "sqlite" && -n "${DB_DATABASE:-}" && "${DB_DATABASE}" != ":memory:" ]]; then
        mkdir -p "$(dirname "$DB_DATABASE")"
        [[ -e "$DB_DATABASE" ]] || touch "$DB_DATABASE"
    fi
}

wait_for_db() {
    local seconds="${WD_DB_WAIT_SECONDS:-60}" i=0
    [[ "${DB_CONNECTION:-sqlite}" == "sqlite" ]] && return 0
    while (( i < seconds )); do
        if php -d display_errors=stderr -r '
            $c = getenv("DB_CONNECTION") ?: "mysql";
            $host = getenv("DB_HOST") ?: "127.0.0.1";
            $port = getenv("DB_PORT") ?: ($c === "pgsql" ? "5432" : "3306");
            $db = getenv("DB_DATABASE") ?: "";
            $dsn = $c === "pgsql" ? "pgsql:host=$host;port=$port;dbname=$db" : "mysql:host=$host;port=$port;dbname=$db";
            try { new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "", [PDO::ATTR_TIMEOUT => 2]); exit(0); }
            catch (Throwable $e) { exit(1); }
        ' 2>/dev/null; then
            return 0
        fi
        (( i == 0 )) && log "warte auf Datenbank ${DB_HOST:-?}:${DB_PORT:-?} …"
        sleep 1; i=$((i + 1))
    done
    log "FEHLER: Datenbank nach ${seconds}s nicht erreichbar."
    return 1
}

build_caches() {
    php artisan package:discover --ansi --quiet
    php artisan config:clear --quiet
    php artisan route:clear --quiet
    php artisan event:clear --quiet
    php artisan view:clear --quiet
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan event:cache --quiet
}

if [[ "$role" == "oneoff" ]]; then
    ensure_skeleton
    exec "$@"
fi

if [[ -z "${APP_KEY:-}" ]]; then
    log "FEHLER: APP_KEY fehlt. Erzeugen mit:"
    log "  docker run --rm <image> php artisan key:generate --show"
    log "und in .env.docker eintragen (docs/on-premise-docker.md)."
    exit 1
fi

ensure_skeleton
wait_for_db

if [[ "$role" == "web" && "${WD_AUTO_MIGRATE:-0}" == "1" ]]; then
    log "WD_AUTO_MIGRATE=1 → Migrationen"
    php artisan migrate --force --no-interaction
    # Globaler Rechte-/Rollenkatalog (idempotent, nicht org-editierbar) + Hilfe-Index.
    php artisan db:seed --class=PermissionsSeeder --force --no-interaction
    php artisan help:reindex --no-interaction
fi

build_caches
log "Rolle ${role}: Caches gebaut, starte: $*"

exec "$@"
