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
        'push' => 'Transferir a contabilidad',
    ],

    'flash' => [
        'pushed' => 'Cliente transferido a contabilidad (ID :id).',
        'failed' => 'La transferencia ha fallado: :msg',
        'no_plugin' => 'No hay ningún sistema contable activo.',
    ],

    'error' => [
        'accounting_leads' => 'La contabilidad posee los datos maestros — no se transfiere nada (ajuste «autoridad de datos maestros»).',
        'no_syncer' => 'El plugin :plugin no transfiere contactos.',
    ],

    'authority' => [
        'workdiary' => 'Dirige workDiary',
        'accounting' => 'Dirige la contabilidad',
    ],
];
