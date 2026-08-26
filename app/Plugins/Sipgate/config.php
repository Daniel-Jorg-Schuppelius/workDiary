<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * sipgate als SMS-Gateway (Feature 147). Deutscher Telefonie-Anbieter mit
 * Betrieb in Deutschland — der zweite EU-Anbieter aus der Bewertung in
 * Feature 070 (G12) und für Organisationen interessant, die sipgate ohnehin
 * für Telefonie nutzen (eine Vertragsbeziehung, ein AVV).
 *
 * API (Stand 2026-08): `POST https://api.sipgate.com/v2/sessions/sms` mit
 * Personal Access Token als HTTP-Basic (`tokenId` : `token`), Body
 * `{smsId, recipient, message}`; Erfolg = HTTP 204 ohne Body — es gibt
 * folglich KEINE Provider-Message-ID und keine Zustellquittung.
 * `smsId` bezeichnet die SMS-Erweiterung des Accounts (z. B. „s0"), nicht
 * die Nachricht. Healthcheck: `GET /v2/account`.
 */

return [
    'enabled' => env('SIPGATE_ENABLED', false),
    'token_id' => env('SIPGATE_TOKEN_ID', ''),
    'token' => env('SIPGATE_TOKEN', ''),
    'api_base' => env('SIPGATE_API_BASE', 'https://api.sipgate.com/v2'),
    'sms_id' => env('SIPGATE_SMS_ID', 's0'),
];
