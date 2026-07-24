<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Calendly (Feature 095). Empfängt extern gebuchte Termine per Webhook + Polling
 | und legt sie als bestätigungspflichtige Terminwünsche (appointment_requests) an.
 | OAuth-Client-ID/-Secret sind installationsweit (ENV) — die per-Org-Tokens liegen
 | in `calendly_connections`. Eingehängt vom CalendlyServiceProvider unter
 | `plugins.calendly`.
 */
return [
    'enabled' => env('CALENDLY_ENABLED', false),
    'client_id' => env('CALENDLY_CLIENT_ID', ''),
    'client_secret' => env('CALENDLY_CLIENT_SECRET', ''),
    'api_base' => env('CALENDLY_API_BASE', 'https://api.calendly.com'),
    'authorize_url' => env('CALENDLY_AUTHORIZE_URL', 'https://auth.calendly.com/oauth/authorize'),
    'token_url' => env('CALENDLY_TOKEN_URL', 'https://auth.calendly.com/oauth/token'),
    // Calendly OAuth kennt keinen granularen Scope-Katalog; leer lassen.
    'scopes' => env('CALENDLY_SCOPES', ''),
    // Polling-Backfill-Fenster (Tage rückwärts/vorwärts um „jetzt").
    'backfill_days_past' => (int) env('CALENDLY_BACKFILL_DAYS_PAST', 7),
    'backfill_days_future' => (int) env('CALENDLY_BACKFILL_DAYS_FUTURE', 60),
];
