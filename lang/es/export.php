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
        'customers' => 'Clientes',
        'projects' => 'Proyectos',
        'users' => 'Usuarios',
        'materials' => 'Materiales',
        'scheduled_shifts' => 'Planes de turnos',
        'tours' => 'Rutas',
    ],
    'format' => [
        'csv' => 'CSV',
        'xlsx' => 'Excel (XLSX)',
    ],
    'state' => [
        'preparing' => 'En preparación',
        'ready' => 'Listo',
        'failed' => 'Fallido',
    ],
];
