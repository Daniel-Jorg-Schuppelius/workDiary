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
    'title' => 'Usage per tenant',
    'subtitle' => 'Users, storage, modules and last activity per organisation — platform operators only.',
    'back' => 'Organisations',
    'empty' => 'No organisations found.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Status',
        'users' => 'Users',
        'active_users' => 'Active (30 days)',
        'storage' => 'Storage',
        'modules' => 'Modules',
        'last_activity' => 'Last activity',
    ],
    'benchmark' => [
        'link' => 'Industry comparison',
        'subtitle' => 'Annual emissions per main industry profile across all tenants excluding demos, only with at least three organisations per industry.',
        'title' => 'Emissions by industry :year (anonymous)',
        'branch' => 'Industry',
        'organizations' => 'Organisations',
        'mean' => 'Mean',
        'median' => 'Median',
        'empty' => 'No industry with at least :min organisations and recorded emissions.',
    ],
];
