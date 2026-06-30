<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : location.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Standortbasierte Zeiterfassung. Standardwerte für neue Geofences sowie die
 * Aufbewahrungsfrist der rohen GPS-Spur (location_points sind PII).
 */

return [
    // Default-Parameter für neu angelegte Geofences (UI/Seeder).
    'defaults' => [
        'radius_m' => (int) env('LOCATION_DEFAULT_RADIUS_M', 100),
        'min_dwell_minutes' => (int) env('LOCATION_DEFAULT_MIN_DWELL', 5),
        'gap_merge_minutes' => (int) env('LOCATION_DEFAULT_GAP_MERGE', 10),
    ],

    // Aufbewahrung der rohen Punkte in Tagen. Nach Ablauf werden bereits
    // verarbeitete location_points gelöscht (location:purge-points); die
    // abgeleiteten Besuche und Buchungen bleiben erhalten. 0 = nie löschen.
    'retention_days' => (int) env('LOCATION_RETENTION_DAYS', 90),
];
