<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : export.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'entity' => [
        'customers' => 'Clienti',
        'projects' => 'Progetti',
        'users' => 'Utenti',
        'materials' => 'Materiali',
        'scheduled_shifts' => 'Piani turni',
        'tours' => 'Giri',
    ],
    'format' => [
        'csv' => 'CSV',
        'xlsx' => 'Excel (XLSX)',
    ],
    'state' => [
        'preparing' => 'In preparazione',
        'ready' => 'Pronto',
        'failed' => 'Fallito',
    ],
];
