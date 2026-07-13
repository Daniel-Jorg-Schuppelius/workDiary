<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * FedEx-Plugin (Feature 059, MVP-128 / Bauturbo A5). Die eigentliche Anbindung
 * (OAuth2-Client-ID/-Secret, Account-Nummer, Sandbox-Schalter) liegt PRO
 * ORGANISATION in `carrier_connections` (carrier = `fedex`) und wird über das
 * Versand-Admin gepflegt. ENV dient nur als globaler Aktivierungs-Fallback
 * für Tests/Konsole.
 *
 * FedEx Ship/Track API (REST, OAuth2 Client-Credentials, Token 60 min): Basis
 *   Prod    https://apis.fedex.com
 *   Sandbox https://apis-sandbox.fedex.com   (self-service, kostenlos)
 * Token: POST /oauth/token (client_id/client_secret im Form-Body). Label als
 * PDF; produktiver Labeldruck erfordert die FedEx-Label-Zertifizierung.
 */

return [
    'enabled' => env('FEDEX_ENABLED', false),
    'base_url' => env('FEDEX_BASE_URL', 'https://apis.fedex.com'),
    'sandbox_base_url' => env('FEDEX_SANDBOX_BASE_URL', 'https://apis-sandbox.fedex.com'),
    // Default-Service und Abgabeart (per ENV auf den Vertrag anpassbar).
    'service_type' => env('FEDEX_SERVICE_TYPE', 'FEDEX_INTERNATIONAL_PRIORITY'),
    'pickup_type' => env('FEDEX_PICKUP_TYPE', 'USE_SCHEDULED_PICKUP'),
];
