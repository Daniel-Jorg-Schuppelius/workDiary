<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : automation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'trigger' => [
        'expense_submitted' => 'Liquidación de gastos enviada',
        'open_issue_created' => 'Punto abierto creado',
        'procedure_deviation_recorded' => 'Desviación de procedimiento registrada',
        'incoming_invoice_received' => 'Factura recibida',
    ],
    'action' => [
        'expense_approve' => 'Aprobar gastos',
        'diary_follow_up' => 'Crear orden de seguimiento',
        'incoming_invoice_discard' => 'Descartar la factura recibida',
        'incoming_invoice_reject' => 'Archivar la factura recibida como «no es una factura»',
        'incoming_invoice_assign' => 'Asignar la factura recibida',
    ],
    'form' => [
        'title' => 'Nueva regla de automatización',
        'trigger' => 'Desencadenante',
        'action' => 'Acción',
        'action_hint' => 'La acción debe corresponder al desencadenante.',
        'conditions' => 'Condiciones (JSON)',
        'conditions_hint' => 'Una condición vacía {"all":[]} se aplica siempre. Ejemplos:',
    ],
    'error' => [
        'invalid_json' => 'Las condiciones no son un objeto JSON válido.',
        'action_trigger_mismatch' => 'Esta acción no corresponde al desencadenante elegido.',
    ],
];
