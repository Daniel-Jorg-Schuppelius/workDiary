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

BACKUP="../license-keys.env.deploybak"

echo "→ Lizenz-Signierschlüssel sichern (falls vorhanden)"
[ -f storage/license-keys.env ] && cp storage/license-keys.env "$BACKUP" || true

echo "→ Code auf origin/main bringen (Hard-Reset – verwirft lokale Änderungen!)"
git fetch origin
git reset --hard origin/main

echo "→ Lizenz-Signierschlüssel zurücklegen + absichern"
if [ -f "$BACKUP" ]; then
    cp "$BACKUP" storage/license-keys.env
    chmod 600 storage/license-keys.env
fi

echo "→ Composer-Abhängigkeiten (ohne dev)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Datenbank-Migrationen"
php artisan migrate --force

echo "→ Caches leeren"
php artisan config:clear
php artisan view:clear

echo "→ Lizenzdateien neu sealen (Public Key aus der .env)"
php artisan license:seal

echo "→ Kontrolle"
php artisan license:show || true

echo "✓ Deploy abgeschlossen"
