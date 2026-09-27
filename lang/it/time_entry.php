<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time_entry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'activity_type' => [
        'project' => 'Progetto',
        'admin' => 'Amministrazione',
        'training' => 'Formazione',
        'meeting' => 'Riunione',
        'internal' => 'Interno',
        'travel' => 'Trasferta',
        'break' => 'Pausa',
        'absence' => 'Assenza',
        'standby' => 'Reperibilità',
        'other' => 'Altro',
    ],
    // Projektvorschläge für offene Zeitblöcke (MVP-923).
    'suggestion' => [
        'source' => [
            'order' => 'Suggerimento: ordine «:title»',
            'day' => 'Suggerimento: oggi «:title»',
            'recent' => 'Suggerimento: ultima registrazione',
        ],
        'book_all' => 'Registra tutti i :count suggerimenti',
        'booked' => ':count blocchi di tempo registrati.',
    ],
];
