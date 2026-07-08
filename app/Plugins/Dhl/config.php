<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * DHL-Paket-Plugin (Feature 059, MVP-128). Die eigentliche Anbindung
 * (GK-Benutzer/Passwort, dhl-api-key, Abrechnungsnummer, Sandbox-Schalter)
 * liegt PRO ORGANISATION in `carrier_connections` (carrier = `dhl`) und wird
 * über das Versand-Admin gepflegt. ENV dient nur als globaler
 * Aktivierungs-Fallback für Tests/Konsole.
 *
 * DHL Parcel DE Shipping API v2 (Label): Basis
 *   Prod    https://api-eu.dhl.com
 *   Sandbox https://api-sandbox.dhl.com
 * Auth = Basic (GK-Zugang) + Header `dhl-api-key`; Sendungsverfolgung über die
 * DHL „Shipment Tracking – Unified" API (`/track/shipments`).
 */

return [
    'enabled' => env('DHL_ENABLED', false),
    'base_url' => env('DHL_BASE_URL', 'https://api-eu.dhl.com'),
    'sandbox_base_url' => env('DHL_SANDBOX_BASE_URL', 'https://api-sandbox.dhl.com'),
    // Default-Paketprodukt (V01PAK = DHL Paket national).
    'product' => env('DHL_PRODUCT', 'V01PAK'),
    'profile' => env('DHL_PROFILE', 'STANDARD_GRUPPENPROFIL'),
];
