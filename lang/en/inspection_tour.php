<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfertouren (MVP-918).
return [
    'title' => 'Inspector tour',
    'subtitle' => 'Plan an inspector\'s due inspections as a tour: each appointment becomes an order at the equipment location, and tour planning optimises the sequence.',
    'inspector' => 'Inspector',
    'due_until' => 'Due by',
    'date' => 'Tour date',
    'select' => 'Select',
    'location' => 'Location',
    'no_coordinates' => 'no coordinates',
    'no_coordinates_hint' => 'Without coordinates on the equipment the stop stays in the tour but is not included in the route calculation.',
    'none' => 'No open inspections for this inspector in the period.',
    'none_selected' => 'Please select at least one open inspection.',
    'unavailable' => 'Tour planning is not active for your organisation; inspector tours require the planning module.',
    'plan' => 'Plan tour',
    'planned' => ':count inspections planned as a tour.',
    'entry_title' => 'Inspection :profile – :asset',
    'entry_content' => 'Inspection from the inspector tour, due on :due.',
];
