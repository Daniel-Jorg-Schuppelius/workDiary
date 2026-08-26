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
 * seven.io SMS-Gateway (Feature 147). Deutscher Anbieter, Rechenzentren in
 * Deutschland, AVV nach Art. 28 DSGVO über das Kundenkonto abschließbar —
 * einer der beiden EU-Anbieter aus der Bewertung in Feature 070 (G12).
 *
 * API (Stand 2026-08): `POST https://gateway.seven.io/api/sms` mit Header
 * `X-Api-Key`, Parameter `to`, `text`, `from`, `foreign_id`, `json=1`;
 * Antwort trägt `success` als Code-String ("100" = angenommen, "101" =
 * teilweise) und je Nachricht `id` und `parts`. Guthaben: `GET /balance`.
 *
 * Der API-Key gehört NIE hierher, sondern in die (verschlüsselten)
 * plugin_settings der Organisation; der ENV-Fallback existiert nur für
 * Single-Tenant-Installationen und Konsolenläufe.
 */

return [
    'enabled' => env('SEVENIO_ENABLED', false),
    'api_key' => env('SEVENIO_API_KEY', ''),
    'api_base' => env('SEVENIO_API_BASE', 'https://gateway.seven.io/api'),
    // Absenderkennung: alphanumerisch max. 11 Zeichen (dann ohne Rückkanal)
    // oder eine eigene Rufnummer in E.164.
    'from' => env('SEVENIO_FROM', ''),
];
