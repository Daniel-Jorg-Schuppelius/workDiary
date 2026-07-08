#!/usr/bin/env bash
#
# Created on   : Sun Jun 28 2026
# Author       : Daniel Jörg Schuppelius
# License      : AGPL-3.0-or-later
#
# Reproduzierbares Security-Release-Gate (Feature 051, MVP-098).
#
# Bündelt die automatisierbaren Prüfungen des Release-Gates in EINEM
# reproduzierbaren Lauf. Bricht beim ersten Fehlschlag ab (kritische/hohe
# Abhängigkeits-Advisories, Lint-, Format- oder Testfehler sperren die Freigabe).
#
# Manuelle Prüfblöcke (Whitebox/dynamisch, 2FA-Vertiefung, unabhängiger Pentest
# = MVP-099/100/101) sind hiermit NICHT abgedeckt und bleiben Voraussetzung für
# die formale Freigabe — siehe docs/security/release-gate-2026-06.md.

set -euo pipefail

cd "$(dirname "$0")/.."

step() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }

step "Secret-/Credential-Scan der Git-Historie (gitleaks)"
if command -v gitleaks >/dev/null 2>&1; then
  gitleaks detect --source . --config .gitleaks.toml --redact --no-banner
else
  printf '\033[1;33m⚠ gitleaks nicht installiert — Schritt lokal übersprungen (CI erzwingt ihn).\n'
  printf '  Installation: https://github.com/gitleaks/gitleaks (z. B. »brew install gitleaks«).\033[0m\n'
fi

step "Composer-Abhängigkeits-Advisories (composer audit)"
composer audit --no-interaction

step "NPM-Abhängigkeits-Advisories (npm audit, prod)"
npm audit --omit=dev

step "OSV-Sicherheitslage (security:advisories-check, Warn-Step)"
# Nicht blockierend: die CI-Datenbank enthält keine Advisory-Daten; auf dem
# Betriebssystem warnt der Step bei offenen high/critical-Advisories.
php artisan security:advisories-check || printf '\033[1;33m⚠ Offene high/critical-Advisories — Admin → Sicherheit prüfen.\033[0m\n'

step "SBOM erzeugen (CycloneDX 1.6: Composer + npm + hierarchischer Merge)"
# Feature 044e (AR §23): echte Dependency-Graphen über die offiziellen
# CycloneDX-Tools; merge-sbom.php ersetzt `cyclonedx-cli merge --hierarchical`
# (kein .NET nötig). Fallback: selbsttragender Eigenbau (composer sbom).
if composer CycloneDX:make-sbom --spec-version=1.6 --output-format=JSON --omit=dev --omit=plugin --output-file=storage/app/sbom-composer.cdx.json \
   && npx @cyclonedx/cyclonedx-npm --omit dev --spec-version 1.6 --output-format JSON --output-file storage/app/sbom-npm.cdx.json; then
  php scripts/merge-sbom.php storage/app/sbom-composer.cdx.json storage/app/sbom-npm.cdx.json storage/app/sbom.cdx.json "${APP_VERSION:-dev}"
else
  printf '\033[1;33m⚠ CycloneDX-Tools nicht verfügbar — Fallback auf Eigenbau (flacher Graph).\033[0m\n'
  php scripts/generate-sbom.php
fi

step "Code-Stil (pint --test)"
vendor/bin/pint --test

step "Statische Analyse (phpstan, Level 8)"
composer lint

step "Testsuite (composer test)"
composer test

printf '\n\033[1;32m✓ Automatisiertes Security-Gate bestanden.\033[0m\n'
printf 'Offen für die Freigabe: manuelle Whitebox-/2FA-Prüfung und unabhängiger Pentest (MVP-099/100/101).\n'
