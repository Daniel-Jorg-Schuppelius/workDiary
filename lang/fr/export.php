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
        'customers' => 'Clients',
        'projects' => 'Projets',
        'users' => 'Utilisateurs',
        'materials' => 'Matériaux',
        'scheduled_shifts' => 'Plannings de service',
        'tours' => 'Tournées',
    ],
    'format' => [
        'csv' => 'CSV',
        'xlsx' => 'Excel (XLSX)',
    ],
    'state' => [
        'preparing' => 'En préparation',
        'ready' => 'Prêt',
        'failed' => 'Échoué',
    ],
];
