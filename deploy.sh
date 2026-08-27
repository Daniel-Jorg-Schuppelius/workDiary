#!/usr/bin/env bash
#
# Deploy-Skript für WorkDiary
# ---------------------------------------
# Richtet das Repo auf origin/main aus (robust auch nach History-Rewrites),
# sichert den Lizenz-Signierschlüssel, migriert und re-sealt die lizenz-
# relevanten Dateien. Re-Sealing ist hier ein FESTER Schritt – damit muss der
# Seal NICHT ins Git (siehe storage/app/private/license-seal.php = gitignored).
#
# Enthält KEINE Secrets: der Public Key wird aus der .env (config) gelesen,
# der Private Key liegt ausschließlich in der gitignored storage/license-keys.env.
#
# Aufruf auf dem Server:  ./deploy.sh
#
set -euo pipefail

cd "$(dirname "$0")"

# Release-Prozess (Vollscan 2026-08-23, J5 — WorkDiary-Architecture/release-prozess.md §3/§4):
# Backup VOR der Migration, Wartungsmodus während des Umbaus, system:health als
# harter Exit-Check, Wartungsmodus erst danach beenden. Abschaltbar für
# Sonderfälle: DEPLOY_SKIP_BACKUP=1 (z. B. direkt nach einem frischen Backup),
# DEPLOY_SKIP_MAINTENANCE=1 (z. B. Erstinstallation ohne Nutzer),
# DEPLOY_BACKUP_DIR=<pfad> (anderes Ziel), DEPLOY_BACKUP_KEEP_DAYS=<n>
# (Aufbewahrung der Pre-Deploy-Stände; Default 7 Tage — kürzer als beim
# Cron-Backup, weil diese Sicherungen im Webspace liegen und je Deploy anfallen).
MAINTENANCE_ON=0
# Der ERR-Trap meldet IMMER, an welchem Schritt es klemmte: früher schwieg er,
# solange der Wartungsmodus noch nicht an war — ein Abbruch in den ersten
# Schritten (Backup, artisan down) sah damit aus, als hätte der Deploy einfach
# nur ein Backup gemacht und sich dann kommentarlos beendet.
finish_maintenance() {
    local rc=$? line="${1:-?}" cmd="${2:-?}"
    echo "✗ Deploy ABGEBROCHEN in Zeile $line (Exit-Code $rc): $cmd" >&2
    if [ "$MAINTENANCE_ON" = "1" ]; then
        echo "⚠ Die Anwendung bleibt im WARTUNGSMODUS (halb migrierter Stand darf nicht online)." >&2
        echo "  Nach Klärung manuell: php artisan up" >&2
    fi
}
trap 'finish_maintenance "$LINENO" "$BASH_COMMAND"' ERR

# Das Pre-Deploy-Backup bleibt beim Webspace: der Deploy läuft als Web-User
# (ISPConfig: web141/web143 …), das systemweite /var/backups/workdiary aus der
# conf gehört dagegen root und ist für ihn nicht beschreibbar. Backup.sh gibt
# einer ausdrücklich gesetzten Umgebungsvariablen Vorrang vor der conf, deshalb
# setzt sich das hier gewählte Ziel durch.
#
# Bevorzugt wird das private/ neben dem Docroot: bei einem ISPConfig-verwalteten
# Web gehört es dem Web-User und ist per HTTP grundsätzlich nicht erreichbar.
# storage/app/private ist demgegenüber nur durch die .htaccess geschützt (die
# mit AllowOverride/mod_rewrite/Apache steht und fällt) und liegt in genau dem
# Baum, den tar gerade sichert. Erkannt wird die Site-STRUKTUR, nicht der
# Servername — und jede Bedingung muss passen, sonst bleibt es beim Webspace.
ispconfig_private_dir() {
    local here parent marker m
    here="$(pwd -P)"
    parent="$(dirname "$here")"
    # Docroot heißt web/, daneben ein private/ …
    [ "$(basename "$here")" = "web" ] || return 1
    [ -d "$parent/private" ] || return 1
    # … zusammen mit mindestens einem weiteren Marker der Site-Struktur …
    marker=0
    for m in log ssl tmp cgi-bin; do
        if [ -d "$parent/$m" ]; then marker=1; break; fi
    done
    [ "$marker" = "1" ] || return 1
    # … und wir dürfen dort schreiben. Sonst ist der bessere Ort knapp verfehlt —
    # das darf nicht als "kein ISPConfig-Web" durchgehen.
    if [ ! -w "$parent/private" ]; then
        echo "  ⚠ $parent/private gefunden, aber für $(id -un) nicht beschreibbar — weiche in den Webspace aus." >&2
        return 1
    fi
    printf '%s' "$parent/private/predeploy-backups"
}

if [ -n "${DEPLOY_BACKUP_DIR:-}" ]; then
    PRE_BACKUP_DIR="$DEPLOY_BACKUP_DIR"
    PRE_BACKUP_NOTE="Vorgabe aus DEPLOY_BACKUP_DIR"
elif PRE_BACKUP_DIR="$(ispconfig_private_dir)"; then
    PRE_BACKUP_NOTE="ISPConfig-Site erkannt — außerhalb des Docroots, per HTTP nicht erreichbar"
else
    PRE_BACKUP_DIR="$PWD/storage/app/private/predeploy-backups"
    PRE_BACKUP_NOTE="Rückfallebene: im Webspace, gesperrt nur über die .htaccess"
fi
echo "→ Backup vor dem Update (DB + Dateien) → $PRE_BACKUP_DIR"
echo "  ($PRE_BACKUP_NOTE)"
if [ "${DEPLOY_SKIP_BACKUP:-0}" = "1" ]; then
    echo "  ⚠ DEPLOY_SKIP_BACKUP=1 — Backup übersprungen."
else
    mkdir -p "$PRE_BACKUP_DIR"
    BACKUP_DIR="$PRE_BACKUP_DIR" BACKUP_KEEP_DAYS="${DEPLOY_BACKUP_KEEP_DAYS:-7}" bash scripts/backup.sh
fi

if [ "${DEPLOY_SKIP_MAINTENANCE:-0}" != "1" ]; then
    echo "→ Wartungsmodus (Betreiber-Bypass über den ausgegebenen Secret-Link)"
    DEPLOY_SECRET="$(php -r 'echo bin2hex(random_bytes(12));')"
    php artisan down --retry=60 --secret="$DEPLOY_SECRET"
    MAINTENANCE_ON=1
    echo "  Bypass: <APP_URL>/$DEPLOY_SECRET"
fi

echo "→ Code auf origin/main bringen (Hard-Reset – verwirft lokale Änderungen!)"
# storage/license-keys.env ist gitignored/untracked → reset --hard lässt sie unberührt.
git fetch origin
git reset --hard origin/main

echo "→ Lizenz-Signierschlüssel absichern (falls vorhanden)"
[ -f storage/license-keys.env ] && chmod 600 storage/license-keys.env || true

echo "→ Composer-Abhängigkeiten (ohne dev)"
# Composer-Cache in die Site legen: das ISPConfig-Home des Web-Users (…/webNNN)
# ist immutable — dort kann Composer sein ~/.cache/composer nicht anlegen
# (die vcs-Klone der Toolkit-Repos brechen sonst hart ab).
export COMPOSER_CACHE_DIR="$PWD/storage/framework/cache/composer"
# Das private Zusatzmodul php-financial-formats ist NICHT Teil der committeten
# composer.lock (siehe AGENTS.md §9.1) — so läuft der Deploy auch für Installationen
# OHNE Zugriff auf das private Repo. Liegt lokal eine composer.local.json (nur
# zahlende Kunden mit Repo-Zugriff), wird das Modul zusätzlich aufgelöst; sonst
# reproduzierbarer Install aus der Lock.
if [ -f composer.local.json ]; then
    echo "  composer.local.json erkannt → privates Zusatzmodul (php-financial-formats) wird mit aufgelöst"
    composer update daniel-jorg-schuppelius/php-financial-formats --with-all-dependencies \
        --no-dev --optimize-autoloader --no-interaction
else
    composer install --no-dev --optimize-autoloader --no-interaction
fi

echo "→ Frontend-Assets bauen (Vite-Manifest für public/build)"
# Pflicht: ohne gebautes Manifest wirft @vite eine ViteManifestNotFoundException
# (HTTP 500). npm ci nutzt package-lock.json für reproduzierbare Installs.
# npm-Cache in die Site legen: das ISPConfig-Home des Web-Users (…/webNNN)
# ist immutable — dort kann npm sein ~/.npm nicht anlegen.
export npm_config_cache="$PWD/storage/framework/cache/npm"
if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
npm run build

echo "→ Storage-Link sicherstellen (public/storage → storage/app/public)"
# Ohne den Symlink sind öffentliche Uploads (Logos, Anhänge) per URL nicht
# erreichbar. Nur anlegen, wenn er fehlt — sonst meldet Laravel einen Fehler
# („link already exists"). Idempotent und still bei vorhandenem Link.
if [ ! -e public/storage ]; then
    php artisan storage:link
else
    echo "  ✓ Storage-Link existiert bereits."
fi

echo "→ Datenbank-Migrationen"
php artisan migrate --force

echo "→ Datenbank-Seeder ausführen"
php artisan db:seed --force

echo "→ Hilfe-Topics indexieren (resources/help/{locale} → help_topics)"
# Ohne diesen Schritt bleibt die Tabelle help_topics leer und die In-App-Hilfe
# (Sidebar) zeigt keine Texte.
php artisan help:reindex

echo "→ Production-Caches bauen (config/route/event)"
# optimize:clear räumt alte Caches weg, dann werden Production-Caches gebaut
# (schneller + konsistent mit scripts/install-webspace.sh). view:cache bleibt
# bewusst außen vor. Hinweis: spätere .env-Änderungen erst nach erneutem
# config:cache wirksam.
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache

echo "→ Queue-Worker neu starten (laufende Worker laden den neuen Code)"
# Ohne Restart arbeiten dauerhaft laufende Worker (Supervisor/systemd) mit dem
# alten Code weiter. Idempotent und unkritisch, wenn kein Worker läuft.
php artisan queue:restart || true

echo "→ Public Key ermitteln (license-keys.env, sonst .env)"
PUBKEY="$(grep -E '^[[:space:]]*LICENSE_PUBLIC_KEY=' storage/license-keys.env 2>/dev/null | head -1 | cut -d= -f2- | tr -d " \"'")"
[ -z "$PUBKEY" ] && PUBKEY="$(grep -E '^[[:space:]]*LICENSE_PUBLIC_KEY=' .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d " \"'")"

echo "→ Lizenzdateien neu sealen"
if [ -n "$PUBKEY" ]; then
    php artisan license:seal --public-key="$PUBKEY"
else
    echo "  ⚠ Kein LICENSE_PUBLIC_KEY gefunden (weder storage/license-keys.env noch .env) – Sealing übersprungen."
fi

echo "→ Integritäts-Baseline neu einfrieren (MVP-439)"
# Nach jedem Deploy ist der Code-Stand ein anderer — ohne frische Baseline
# meldet der nächtliche integrity:verify dauerhaft Exit 2 (MissingBaseline)
# bzw. Abweichungen. Der Freeze verankert den soeben deployten Stand.
# --yes: nicht-interaktiv eine vorhandene Baseline überschreiben.
# Ein Fehlschlag bricht den Deploy nicht ab, muss aber sichtbar sein —
# sonst schlägt erst der nächtliche integrity:verify Alarm.
if ! php artisan integrity:freeze --yes; then
    echo "  ⚠ integrity:freeze FEHLGESCHLAGEN — Baseline veraltet/fehlt."
    echo "    Manuell nachholen: php artisan integrity:freeze --yes && php artisan integrity:verify"
fi

echo "→ Release-Manifest (Versionen, Prüfsummen; optional signiert)"
# Kein Abbruchgrund: das Manifest ist Nachweis, nicht Betriebsvoraussetzung.
php artisan release:manifest || echo "  ⚠ release:manifest fehlgeschlagen — Nachweis manuell erzeugen."

echo "→ Health-Check nach dem Update (harter Exit-Check)"
# Exit 1 = Problem (DB, offene Migrationen, Storage, Queue, APP_KEY, Mail,
# Lizenz) → Deploy bricht ab, Wartungsmodus bleibt aktiv.
php artisan system:health

echo "→ Kontrolle"
php artisan license:show || true

if [ "$MAINTENANCE_ON" = "1" ]; then
    echo "→ Wartungsmodus beenden"
    php artisan up
    MAINTENANCE_ON=0
fi

echo "✓ Deploy abgeschlossen"
