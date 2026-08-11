#!/usr/bin/env bash
#
# Deploy-Skript für work.schuppelius.org
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

echo "→ Code auf origin/main bringen (Hard-Reset – verwirft lokale Änderungen!)"
# storage/license-keys.env ist gitignored/untracked → reset --hard lässt sie unberührt.
git fetch origin
git reset --hard origin/main

echo "→ Lizenz-Signierschlüssel absichern (falls vorhanden)"
[ -f storage/license-keys.env ] && chmod 600 storage/license-keys.env || true

echo "→ Composer-Abhängigkeiten (ohne dev)"
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
php artisan integrity:freeze --yes || true

echo "→ Kontrolle"
php artisan license:show || true

echo "✓ Deploy abgeschlossen"
