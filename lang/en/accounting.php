<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'action' => [
        'push' => 'Transfer to accounting',
    ],

    'flash' => [
        'pushed' => 'Customer transferred to accounting (ID :id).',
        'failed' => 'Transfer failed: :msg',
        'no_plugin' => 'No accounting system is active.',
    ],

    'error' => [
        'accounting_leads' => 'Accounting owns the master data — nothing is transferred (setting “master data authority”).',
        'no_syncer' => 'The :plugin plugin does not transfer contacts.',
    ],

    'authority' => [
        'workdiary' => 'workDiary leads',
        'accounting' => 'Accounting leads',
    ],
];
