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
        'index' => 'Feuille de temps',
        'show' => 'Feuille de temps n°:id',
    ],
    'fields' => [
        'date' => 'Date',
        'project' => 'Projet',
        'user' => 'Employé',
        'status' => 'Statut',
        'started_at' => 'Début',
        'ended_at' => 'Fin',
        'break_minutes' => 'Pause (min)',
        'duration' => 'Durée',
        'kind' => 'Type',
        'description' => 'Description',
        'notes' => 'Notes',
    ],
    'totals' => [
        'work' => 'Total travail',
        'break' => 'Total pause',
        'material_net' => 'Total matériel (net)',
    ],
    'sections' => [
        'entries' => 'Saisies de temps',
        'materials' => 'Matériaux',
        'customer_release' => 'Validation client',
        'notes' => 'Notes',
    ],
    'signature' => [
        'signed_at' => 'Signé le :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256 : :hash',
        'none' => '— aucune signature —',
    ],
];
