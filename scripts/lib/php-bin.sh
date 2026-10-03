#!/usr/bin/env bash
#
# PHP-Interpreter für die Shell-Skripte des Projekts (deploy.sh, scripts/*.sh).
#
# Auf Servern mit mehreren PHP-Versionen zeigt `php` oft auf eine ältere als
# die, mit der das Web läuft. Gesucht wird deshalb zuerst das versionierte
# Binary passend zu `require.php` der composer.json (php8.5), erst danach `php`.
#
# Nach `source`:
#   PHP_BIN="$(resolve_php_bin "$APP_DIR" "${PHP_BIN:-}")"
#   run_composer install --no-dev

# Mindestversion "X.Y" aus require.php (^8.5, >=8.5, ~8.5.0); leer, wenn nicht
# ermittelbar — dann gilt jedes lauffähige PHP als passend.
php_min_version() {
    grep -m1 -E '^[[:space:]]*"php"[[:space:]]*:' "$1/composer.json" 2>/dev/null \
        | grep -oE '[0-9]+\.[0-9]+' | head -1 || true
}

# "X.Y" des Binaries; leer, wenn es sich nicht starten lässt.
php_version_of() {
    "$1" -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION, "\n";' 2>/dev/null \
        | grep -oE '^[0-9]+\.[0-9]+$' | tail -1 || true
}

# Caret-Semantik wie in der composer.json: gleiche Hauptversion, Minor ab Minimum.
php_meets_minimum() {
    local have min="$2"
    have="$(php_version_of "$1")"
    [ -n "$have" ] || return 1
    [ -n "$min" ] || return 0
    [ "${have%%.*}" -eq "${min%%.*}" ] && [ "${have#*.}" -ge "${min#*.}" ]
}

# resolve_php_bin <app-dir> [wunsch]
# Gibt den Pfad des PHP-Binaries aus. Ein Wunsch (PHP_BIN des Aufrufers) hat
# Vorrang, solange er die Mindestversion erfüllt; sonst wird gesucht — mit dem
# unpassenden Binary bräche Composers Plattform-Check ohnehin jeden Aufruf ab.
resolve_php_bin() {
    local app_dir="${1:-.}" wish="${2:-}" min candidate bin seen=""
    min="$(php_min_version "$app_dir")"

    if [ -n "$wish" ]; then
        if bin="$(command -v "$wish" 2>/dev/null)" && php_meets_minimum "$bin" "$min"; then
            printf '%s' "$bin"
            return 0
        fi
        echo "⚠ PHP_BIN=$wish erfüllt PHP ${min:-?} aus der composer.json nicht — suche ein passendes Binary." >&2
    fi

    for candidate in ${min:+"php$min"} php ${min:+"/usr/bin/php$min" "/usr/local/bin/php$min"}; do
        bin="$(command -v "$candidate" 2>/dev/null)" || continue
        if php_meets_minimum "$bin" "$min"; then
            printf '%s' "$bin"
            return 0
        fi
        seen="$seen $bin=$(php_version_of "$bin")"
    done

    echo "FEHLER: kein PHP ${min:-} gefunden (gesucht: ${min:+php$min, }php;${seen:+ unpassend:$seen})." >&2
    echo "        Passendes Binary vorgeben: PHP_BIN=/pfad/zu/php" >&2
    return 1
}

# Composer mit $PHP_BIN starten: `composer` trägt den Shebang
# `#!/usr/bin/env php` und liefe sonst mit dem Standard-php der Shell.
# Ein Wrapper ohne PHP-Shebang wählt seinen Interpreter selbst.
run_composer() {
    local bin first=""
    bin="$(command -v composer 2>/dev/null)" || { echo "FEHLER: composer nicht gefunden." >&2; return 127; }
    IFS= read -r first < "$bin" 2>/dev/null || true
    case "$first" in
        '#!'*php* | '<?php'*) "$PHP_BIN" "$bin" "$@" ;;
        *) "$bin" "$@" ;;
    esac
}
