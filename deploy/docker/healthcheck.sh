#!/usr/bin/env bash
#
# Container-Healthcheck (MVP-720). Dasselbe Image läuft als php-fpm, Queue-
# Worker und Scheduler — nur der php-fpm-Prozess (PID 1) wird per FastCGI
# gegen Laravels Health-Route /up (bootstrap/app.php) geprüft; für alle anderen
# Rollen genügt „Prozess lebt" (der Container-Restart übernimmt den Rest).

set -u

if ! tr '\0' ' ' < /proc/1/cmdline 2>/dev/null | grep -q 'php-fpm'; then
    exit 0
fi

response="$(
    REQUEST_METHOD=GET \
    SCRIPT_NAME=/index.php \
    SCRIPT_FILENAME=/var/www/html/public/index.php \
    DOCUMENT_ROOT=/var/www/html/public \
    REQUEST_URI=/up \
    SERVER_NAME=localhost \
    SERVER_PORT=80 \
    HTTP_HOST=localhost \
    cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null
)" || exit 1

[[ -n "$response" ]] || exit 1
# php-fpm liefert bei 200 keine Status-Zeile; jede 4xx/5xx-Antwort ist ungesund.
if printf '%s' "$response" | grep -qiE '^Status: *[45][0-9]{2}'; then
    exit 1
fi

exit 0
