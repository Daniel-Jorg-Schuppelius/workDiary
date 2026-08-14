<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : export.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'entity' => [
        'customers' => 'Kunden',
        'projects' => 'Projekte',
        'users' => 'Benutzer',
        'materials' => 'Material',
        'scheduled_shifts' => 'Schichtpläne',
        'tours' => 'Touren',
    ],

    'format' => [
        'csv' => 'CSV',
        'xlsx' => 'Excel (XLSX)',
    ],

    'state' => [
        'preparing' => 'Wird erstellt',
        'ready' => 'Bereit',
        'failed' => 'Fehlgeschlagen',
    ],
];
