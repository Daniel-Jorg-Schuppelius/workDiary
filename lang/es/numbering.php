<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactivo – asigna workDiary)',
        'column' => 'Asignado por',
        'page_hint' => 'Si un programa de contabilidad asigna los números de cliente o de proveedor, un registro nuevo recibe su número al transferirse. Los números existentes no cambian. Los números de factura se configuran en los ajustes del programa.',
    ],
    'field' => [
        'locked_hint' => ':program asigna el número al transferir.',
    ],
    'flash' => [
        'issued' => 'Número :number tomado de :program.',
        'pending' => 'El número de :program llegará con la próxima transferencia: :reason',
        'conflict' => 'El número :number de :program ya está asignado a :holder. Encontrará el caso en la bandeja de asignación.',
    ],
    'conflict' => [
        'title' => 'El número :number de :program ya está asignado a :holder',
    ],
    'error' => [
        'taken_remote' => 'El número :number ya está asignado a «:name» en :program.',
        'taken_local' => 'El número :number ya está asignado a :holder. Cambie primero el número allí.',
        'no_number' => ':program no ha devuelto ningún número.',
    ],
];
