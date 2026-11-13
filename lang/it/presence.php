<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : presence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669).
return [
    'board' => 'Presenze attuali',
    'kind' => [
        'do_not_disturb' => 'Non disturbare',
        'brief_away' => 'Assente per poco',
        'out_of_office' => 'Fuori ufficio',
    ],
    'until' => ':kind fino alle :time',
    'validation' => [
        'until_required' => 'Questo stato richiede una fine.',
        'until_range' => 'La fine deve essere nel futuro e al massimo tra :hours ore.',
    ],
    'dialog' => [
        'title' => 'Il mio stato',
        'submit' => 'Imposta stato',
        'hint' => 'Viene mostrato solo il suo stato attuale, senza motivo e senza cronologia. Termina all’orario indicato, alla timbratura di uscita o quando lo reimposta.',
        'current' => 'Attuale',
        'kind' => 'Stato',
        'duration' => 'Per',
        'no_duration' => 'Senza fine (fino alla reimpostazione o alla timbratura di uscita)',
        'until' => 'Oppure esattamente fino a',
        'until_hint' => 'Ha la precedenza su «Per». «Assente per poco» e «Fuori ufficio» richiedono una fine.',
    ],
    'duration' => [
        '15' => '15 minuti',
        '30' => '30 minuti',
        '60' => '1 ora',
        '120' => '2 ore',
        '240' => '4 ore',
        '480' => '8 ore',
    ],
    'action' => [
        'set' => 'Il mio stato',
        'clear' => 'Reimposta stato',
    ],
    'flash' => [
        'set' => 'Il suo stato «:status» è impostato.',
        'cleared' => 'Il suo stato è stato reimpostato.',
    ],
];
