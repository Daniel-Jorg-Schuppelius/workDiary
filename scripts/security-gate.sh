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

step "Composer-Abhängigkeits-Advisories (composer audit)"
composer audit --no-interaction

step "NPM-Abhängigkeits-Advisories (npm audit, prod)"
npm audit --omit=dev

step "SBOM erzeugen (CycloneDX, Composer + npm)"
php scripts/generate-sbom.php

step "Code-Stil (pint --test)"
vendor/bin/pint --test

step "Statische Analyse (phpstan, Level 8)"
composer lint

step "Testsuite (composer test)"
composer test

printf '\n\033[1;32m✓ Automatisiertes Security-Gate bestanden.\033[0m\n'
printf 'Offen für die Freigabe: manuelle Whitebox-/2FA-Prüfung und unabhängiger Pentest (MVP-099/100/101).\n'
