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
composer install --no-dev --optimize-autoloader --no-interaction

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
# erreichbar. Idempotent — vorhandener Link bleibt bestehen.
php artisan storage:link || true

echo "→ Datenbank-Migrationen"
php artisan migrate --force

echo "→ Datenbank-Seeder ausführen"
php artisan db:seed --force

echo "→ Hilfe-Topics indexieren (resources/help/{locale} → help_topics)"
# Ohne diesen Schritt bleibt die Tabelle help_topics leer und die In-App-Hilfe
# (Sidebar) zeigt keine Texte.
php artisan help:reindex

echo "→ Caches leeren"
php artisan config:clear
php artisan view:clear

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

echo "→ Kontrolle"
php artisan license:show || true

echo "✓ Deploy abgeschlossen"
