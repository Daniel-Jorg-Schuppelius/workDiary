<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Google-Kalender-Plugin (MVP-328, Bauturbo A8). Nur-Publish-Pilot:
 * WorkDiary-Termine → Google Calendar API v3 (events insert/update/delete
 * mit deterministischer Event-ID aus der stabilen UID — sha1-Hex liegt im
 * base32hex-Alphabet der API), OAuth2 Authorization-Code + PKCE mit
 * access_type=offline (Refresh-Token).
 *
 * Scopes: calendar.events (Event-CRUD) + calendar.calendarlist.readonly
 * (Ziel-Kalender-Auswahl + billige Health-Probe) — beide „sensitive".
 * Externe Hürde (Welle C): Google-OAuth-Brand-Verification (~10 Tage,
 * Demo-Video); unverifiziert max. 100 Nutzer, Workspace-interner
 * Consent-Typ „Internal" braucht keine Verification.
 *
 * Client-ID/-Secret sind INSTALLATIONS-weit (ENV) — nie je Organisation.
 */

return [
    'enabled' => env('GOOGLE_CALENDAR_ENABLED', false),
    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', ''),
    'api_base' => env('GOOGLE_CALENDAR_API_BASE', 'https://www.googleapis.com/calendar/v3'),
    'authorize_url' => env('GOOGLE_CALENDAR_AUTHORIZE_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
    'token_url' => env('GOOGLE_CALENDAR_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
    'scopes' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.calendarlist.readonly',
];
