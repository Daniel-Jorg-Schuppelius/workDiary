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
 * Peppol-Access-Point-Anbindung (Feature 066, MVP-734).
 *
 * WorkDiary betreibt KEINEN eigenen AS4-Access-Point — Zertifizierung, PKI und
 * Betrieb bleiben beim Provider. Angebunden wird ein zertifizierter Provider
 * über eine generische REST-Naht: weil zum Bauzeitpunkt kein konkreter
 * Providervertrag vorliegt, kommen Pfade und Feldnamen aus der Konfiguration
 * statt aus geratenen Konstanten. Die Feinabstimmung mit dem tatsächlich
 * gewählten Provider ist ein Pilotschritt (Zugangsdaten, Fehlercodes,
 * Quittungsformat).
 *
 * ENV-Werte sind nur Fallback für Konsolen-/Testkontexte; produktiv liegen die
 * Zugangsdaten in `plugin_settings` (dort at-rest verschlüsselt).
 */

return [
    'enabled' => env('PEPPOL_AP_ENABLED', false),

    // --- Provider-Naht (generischer REST-Adapter) ---
    'base_url' => env('PEPPOL_AP_BASE_URL', ''),
    'api_key' => env('PEPPOL_AP_API_KEY', ''),
    'auth_header' => env('PEPPOL_AP_AUTH_HEADER', 'Authorization'),
    'auth_scheme' => env('PEPPOL_AP_AUTH_SCHEME', 'Bearer'),
    'send_path' => env('PEPPOL_AP_SEND_PATH', '/outbox'),
    'receive_path' => env('PEPPOL_AP_RECEIVE_PATH', '/inbox'),
    'ack_path' => env('PEPPOL_AP_ACK_PATH', '/inbox/{messageId}/acknowledge'),
    'health_path' => env('PEPPOL_AP_HEALTH_PATH', '/status'),

    // Feldnamen im JSON des Providers. Leerer `payload_field` = der SBDH-
    // Umschlag geht als roher XML-Body raus (application/xml) statt in JSON.
    'payload_field' => env('PEPPOL_AP_PAYLOAD_FIELD', 'document'),
    'message_id_field' => env('PEPPOL_AP_MESSAGE_ID_FIELD', 'messageId'),
    'status_field' => env('PEPPOL_AP_STATUS_FIELD', 'status'),
    'items_field' => env('PEPPOL_AP_ITEMS_FIELD', 'documents'),

    // --- Eigene Peppol-Identität ---
    'sender_participant_id' => env('PEPPOL_AP_SENDER_ID', ''),
    'sender_country' => env('PEPPOL_AP_SENDER_COUNTRY', 'DE'),

    // --- Teilnehmerauflösung (SML/SMP) ---
    'sml_zone' => env('PEPPOL_AP_SML_ZONE', \ERechnungToolkit\Enums\SmlZone::PRODUCTION->value),
    'lookup_ttl_hours' => (int) env('PEPPOL_AP_LOOKUP_TTL_HOURS', 24),
];
