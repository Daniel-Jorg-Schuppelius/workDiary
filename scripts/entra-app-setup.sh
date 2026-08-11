#!/usr/bin/env bash
#
# Entra-App-Registrierung für workDiary automatisch anlegen/aktualisieren
# ----------------------------------------------------------------------
# Ersetzt das manuelle Zusammenklicken im Entra-Portal (Runbook §1,
# WorkDiary-Architecture/ms365-pilot-runbook-2026-08.md): legt die
# App-Registrierung an (oder aktualisiert die vorhandene gleichen Namens),
# setzt alle Redirect-URIs und delegierten Graph-Berechtigungen und gibt
# die ENV-/Plugin-Werte aus.
#
# Voraussetzungen:
#   - Azure CLI installiert (apt install azure-cli | https://aka.ms/azcli)
#   - az login  (Konto darf Apps registrieren — Standardnutzer dürfen das,
#     sofern der Tenant es nicht eingeschränkt hat; sonst Entra-Admin)
#
# Aufruf:
#   ./scripts/entra-app-setup.sh                  # anlegen/aktualisieren
#   NEW_SECRET=1 ./scripts/entra-app-setup.sh     # zusätzlich neues Secret
#   WITH_PRESENCE=1 WITH_SHARED_MAIL=1 ...        # optionale Scopes
#   BASE_URL=https://andere-instanz ...           # andere Instanz-URL
#
set -euo pipefail

APP_NAME="${APP_NAME:-workDiary}"
BASE_URL="${BASE_URL:-https://app.workdiary.org}"
GRAPH_SP="00000003-0000-0000-c000-000000000000" # Microsoft Graph

command -v az >/dev/null || { echo "✗ Azure CLI (az) fehlt — Installation: https://aka.ms/azcli"; exit 1; }
az account show >/dev/null 2>&1 || { echo "✗ Nicht angemeldet — bitte zuerst: az login"; exit 1; }

REDIRECTS=(
    "$BASE_URL/admin/msgraph/oauth/callback"
    "$BASE_URL/admin/msgraph/adminconsent/callback"
    "$BASE_URL/admin/msgraph/contacts/oauth/callback"
    "$BASE_URL/admin/msgraph/tasks/oauth/callback"
    "$BASE_URL/admin/msgraph/mail/oauth/callback"
    "$BASE_URL/admin/cloud-intake/microsoft/oauth/callback"
    "$BASE_URL/admin/backup-targets/microsoft/oauth/callback"
    "$BASE_URL/admin/sharepoint/oauth/callback"
)

SCOPES=(User.Read offline_access Calendars.ReadWrite Files.Read.All Sites.Read.All
    Files.ReadWrite Mail.Send Mail.ReadWrite Contacts.ReadWrite Tasks.ReadWrite)
[ "${WITH_PRESENCE:-0}" = "1" ] && SCOPES+=(Presence.Read.All User.ReadBasic.All)
[ "${WITH_SHARED_MAIL:-0}" = "1" ] && SCOPES+=(Mail.Send.Shared)

echo "→ App-Registrierung $APP_NAME suchen …"
APP_ID="$(az ad app list --display-name "$APP_NAME" --query "[0].appId" -o tsv)"

if [ -z "$APP_ID" ]; then
    echo "→ Neu anlegen (Multi-Tenant, Web-Redirects) …"
    APP_ID="$(az ad app create \
        --display-name "$APP_NAME" \
        --sign-in-audience AzureADMultipleOrgs \
        --web-redirect-uris "${REDIRECTS[@]}" \
        --query appId -o tsv)"
else
    echo "→ Vorhandene App ($APP_ID) aktualisieren — Redirect-URIs werden ERSETZT …"
    az ad app update --id "$APP_ID" --web-redirect-uris "${REDIRECTS[@]}"
fi

echo "→ Delegierte Graph-Berechtigungen setzen …"
for scope in "${SCOPES[@]}"; do
    SCOPE_ID="$(az ad sp show --id "$GRAPH_SP" \
        --query "oauth2PermissionScopes[?value=='$scope'].id | [0]" -o tsv)"
    if [ -z "$SCOPE_ID" ]; then
        echo "  ⚠ Scope $scope nicht gefunden — übersprungen"
        continue
    fi
    az ad app permission add --id "$APP_ID" --api "$GRAPH_SP" \
        --api-permissions "$SCOPE_ID=Scope" 2>/dev/null \
        && echo "  + $scope" || echo "  ✓ $scope (bereits vorhanden)"
done

SECRET_HINT="(unverändert — mit NEW_SECRET=1 neu erzeugen)"
if [ "${NEW_SECRET:-0}" = "1" ]; then
    echo "→ Neues Client-Secret (24 Monate) …"
    SECRET="$(az ad app credential reset --id "$APP_ID" \
        --display-name "workdiary-$(date +%Y%m%d)" --years 2 \
        --query password -o tsv)"
    SECRET_HINT="$SECRET"
fi

TENANT_ID="$(az account show --query tenantId -o tsv)"

cat <<EOF

✓ Fertig. Werte für .env (Instanz-App) ODER die Msgraph-Plugin-Seite (Org-App):

  MSGRAPH_ENABLED=true
  MSGRAPH_CLIENT_ID=$APP_ID
  MSGRAPH_CLIENT_SECRET=$SECRET_HINT
  MSGRAPH_TENANT=common        # Instanz-App; Plugin-Seite alternativ: $TENANT_ID

Nächste Schritte:
  1. Admin-Zustimmung: Button im Msgraph-Panel (/admin/msgraph) — oder:
     az ad app permission admin-consent --id $APP_ID
  2. Verbinden je Block auf /admin/msgraph, Testfolge: Runbook §3.
EOF
