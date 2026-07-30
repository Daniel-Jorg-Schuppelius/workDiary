<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : passenger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Personenbeförderung (MVP-456, Branchenprofil Taxi/Mietwagen)
    |--------------------------------------------------------------------------
    |
    | Mobilitätsdaten nach § 3a PBefG / MDV: ob die Organisation zur
    | Bereitstellung verpflichtet ist, wird KONFIGURIERT — nie geraten
    | (Konzept §9). Die Einzelunternehmer-Ausnahme wird explizit gesetzt.
    | Die eigentliche Übertragung läuft über ein Plugin mit der Capability
    | `mobility_data` (MobilityDataPublisher-Vertrag).
    |
    */
    'mobility_data' => [
        'obligated' => (bool) env('PASSENGER_MOBILITY_DATA_OBLIGATED', false),
        'sole_proprietor_exempt' => (bool) env('PASSENGER_MOBILITY_SOLE_EXEMPT', true),
    ],

    /*
    | 50-km-Grenze des ermäßigten Steuersatzes für Taxifahrten
    | (§ 12 Abs. 2 Nr. 10 UStG) — als Konfigurationswert, damit die
    | Steuerentscheidung im Fahrtabschluss nachvollziehbar bleibt.
    */
    'tax' => [
        'reduced_rate_max_km' => (float) env('PASSENGER_TAX_REDUCED_MAX_KM', 50),
    ],
];
