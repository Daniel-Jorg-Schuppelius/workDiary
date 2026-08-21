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
        'push' => 'Trasferisci alla contabilità',
    ],

    'flash' => [
        'pushed' => 'Cliente trasferito alla contabilità (ID :id).',
        'failed' => 'Trasferimento non riuscito: :msg',
        'no_plugin' => 'Nessun sistema contabile attivo.',
    ],

    'error' => [
        'accounting_leads' => 'La contabilità detiene i dati anagrafici — non viene trasferito nulla (impostazione «autorità dei dati anagrafici»).',
        'no_syncer' => 'Il plugin :plugin non trasferisce contatti.',
    ],

    'authority' => [
        'workdiary' => 'Guida workDiary',
        'accounting' => 'Guida la contabilità',
    ],
];
