<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Uso por cliente',
    'subtitle' => 'Usuarios, almacenamiento, módulos y última actividad por organización — solo para la operación de la plataforma.',
    'back' => 'Organizaciones',
    'empty' => 'No hay organizaciones.',
    'field' => [
        'organization' => 'Organización',
        'status' => 'Estado',
        'users' => 'Usuarios',
        'active_users' => 'Activos (30 días)',
        'storage' => 'Almacenamiento',
        'modules' => 'Módulos',
        'last_activity' => 'Última actividad',
    ],
    'benchmark' => [
        'link' => 'Comparación sectorial',
        'subtitle' => 'Emisiones anuales por perfil sectorial principal de todos los clientes sin demos, solo a partir de tres organizaciones por sector.',
        'title' => 'Emisiones por sector :year (anónimo)',
        'branch' => 'Sector',
        'organizations' => 'Organizaciones',
        'mean' => 'Media',
        'median' => 'Mediana',
        'empty' => 'Ningún sector con al menos :min organizaciones y emisiones registradas.',
    ],
];
