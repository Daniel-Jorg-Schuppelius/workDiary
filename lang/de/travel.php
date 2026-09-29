<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : travel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'vehicle' => [
        'company' => 'Firmenwagen',
        'private' => 'Privat-PKW',
        'rental' => 'Mietwagen',
        'public_transport' => 'ÖPNV',
        'bicycle' => 'Fahrrad',
        'foot' => 'zu Fuß',
        'other' => 'Sonstiges',
    ],
    'trip_kind' => [
        'business' => 'Betrieblich',
        'commute' => 'Wohnung–Arbeitsstätte',
        'private' => 'Privat',
    ],
    'tour_leg_purpose' => 'Etappe zu :title',
    'tour_return_purpose' => 'Rückfahrt',
    'driving_time' => [
        'legend' => 'Lenk- und Ruhezeiten',
        'hint' => 'Für Fahrzeuge mit Lenkzeitregeln: zweiter Fahrer im Mehrfahrerbetrieb und Überfahrten, bei denen das Fahrzeug auf Fähre oder Zug mitfährt.',
        'co_driver' => 'Zweiter Fahrer (Mehrfahrerbetrieb)',
        'co_driver_none' => '— allein unterwegs —',
        'ferry_or_train' => 'Fähre/Zug: Fahrzeug fährt mit, keine Lenkzeit',
    ],
];
