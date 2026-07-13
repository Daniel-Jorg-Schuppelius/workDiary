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
 * UPS-Plugin (Feature 059, MVP-128 / Bauturbo A5). Die eigentliche Anbindung
 * (OAuth2-Client-ID/-Secret, Shipper-Nummer, Sandbox-Schalter) liegt PRO
 * ORGANISATION in `carrier_connections` (carrier = `ups`) und wird über das
 * Versand-Admin gepflegt. ENV dient nur als globaler Aktivierungs-Fallback
 * für Tests/Konsole.
 *
 * UPS Shipping/Track API (REST, OAuth2 Client-Credentials — Access-Keys sind
 * seit 06/2024 abgeschaltet): Basis
 *   Prod    https://onlinetools.ups.com
 *   Sandbox https://wwwcie.ups.com   (Customer Integration Environment)
 * Token: POST /security/v1/oauth/token (Basic client_id:client_secret,
 * grant_type=client_credentials). Label liefert UPS als GIF/ZPL/EPL —
 * KEIN PDF; wir fordern GIF an.
 */

return [
    'enabled' => env('UPS_ENABLED', false),
    'base_url' => env('UPS_BASE_URL', 'https://onlinetools.ups.com'),
    'sandbox_base_url' => env('UPS_SANDBOX_BASE_URL', 'https://wwwcie.ups.com'),
    // API-Version des Shipping-Endpunkts (Pfadbestandteil).
    'version' => env('UPS_API_VERSION', 'v2409'),
    // Default-Service (11 = UPS Standard, EU-Bodenfracht).
    'service' => env('UPS_SERVICE', '11'),
];
