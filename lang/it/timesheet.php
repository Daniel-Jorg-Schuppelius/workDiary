<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : timesheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'titles' => [
        'index' => 'Foglio ore',
        'show' => 'Foglio ore #:id',
    ],
    'fields' => [
        'date' => 'Data',
        'project' => 'Progetto',
        'user' => 'Dipendente',
        'status' => 'Stato',
        'started_at' => 'Inizio',
        'ended_at' => 'Fine',
        'break_minutes' => 'Pausa (min)',
        'duration' => 'Durata',
        'kind' => 'Tipo',
        'description' => 'Descrizione',
        'notes' => 'Note',
    ],
    'totals' => [
        'work' => 'Totale lavoro',
        'break' => 'Totale pausa',
        'material_net' => 'Totale materiale (netto)',
    ],
    'sections' => [
        'entries' => 'Registrazioni di tempo',
        'materials' => 'Materiali',
        'customer_release' => 'Approvazione cliente',
        'notes' => 'Note',
    ],
    'signature' => [
        'signed_at' => 'Firmato il :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256: :hash',
        'none' => '— nessuna firma —',
    ],
];
