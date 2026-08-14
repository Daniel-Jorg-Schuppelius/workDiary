<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : flex.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'eligibility' => [
        'title' => 'Elegibilidad flex para :name',
        'nav_title' => 'Elegibilidad flex',
        'subtitle' => 'Periodos durante los cuales :name participa en el registro de tiempo flex.',
        'current' => [
            'active' => 'Actualmente elegible para flex',
            'inactive' => 'Actualmente no elegible para flex',
        ],
        'table' => [
            'valid_from' => 'Válido desde',
            'valid_to' => 'Válido hasta',
            'open' => 'sin fin',
            'note' => 'Nota',
            'actions' => 'Acciones',
        ],
        'form' => [
            'add_title' => 'Añadir nuevo periodo',
            'valid_from' => 'Válido desde',
            'valid_to' => 'Válido hasta (vacío = sin fin)',
            'note' => 'Nota (opcional)',
            'submit' => 'Crear periodo',
            'end_today' => 'Finalizar hoy',
            'end_submit' => 'Finalizar',
        ],
        'flash' => [
            'saved' => 'Periodo flex guardado.',
            'deleted' => 'Periodo flex eliminado.',
        ],
        'empty' => ':name no tiene periodos flex registrados — no participa en el tiempo flex.',
        'confirm_delete' => '¿Eliminar realmente este periodo? Los cálculos de saldo se recalcularán.',
    ],
];
