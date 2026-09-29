<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sepa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Remesas de pago',
    'subtitle' => 'Transferencias y adeudos agrupados como fichero SEPA',
    'empty' => 'Todavía no se ha creado ninguna remesa.',
    'no_items' => 'No hay posiciones en la remesa.',
    'run_created' => 'Remesa creada.',
    'direct_debit_created' => 'Adeudo domiciliado creado.',
    'direct_debit_title' => 'Adeudo domiciliado',
    'direct_debit_hint' => 'Cobro con un mandato activo; la fecha más temprana resulta del plazo previo.',
    'direct_debit_mandate' => 'Mandato',
    'direct_debit_amount' => 'Importe',
    'direct_debit_reference' => 'Concepto',
    'direct_debit_submit' => 'Crear cobro',
    'direct_debit_invoice' => 'Factura',
    'direct_debit_invoice_none' => '— sin referencia de factura —',
    'direct_debit_from_invoice' => 'Cobrar por domiciliación',
    'run_released' => 'Remesa aprobada.',
    'run_cancelled' => 'Remesa anulada.',
    'item_removed' => 'Posición eliminada.',
    'item_adjusted' => 'Importe de pago ajustado.',
    'confirm_release' => '¿Aprobar la remesa con :count posiciones?',
    'confirm_cancel' => '¿Anular la remesa? Las facturas vuelven a ser pagaderas.',
    'released_by' => 'Aprobada por',
    'file_hash' => 'Hash del fichero (SHA-256)',
    'execution_hint' => 'Fecha propuesta; el banco ejecuta como muy pronto ese día.',
    'discount_used' => 'Descuento :percent %',
    'adjust_hint' => 'Importe facturado: :gross. Un pago menor requiere un motivo.',
    'reference' => 'Factura :number',
    'reference_unknown' => 'Factura sin número',
    'document_description' => 'Fichero SEPA de la remesa :id',

    'proposal' => [
        'title' => 'Propuesta de pago',
        'subtitle' => 'Facturas recibidas aprobadas con la fecha de ejecución más ventajosa',
        'empty' => 'No hay facturas abiertas aprobadas para el pago.',
    ],

    'action' => [
        'confirm_iban' => 'Confirmar IBAN',
        'proposal' => 'Propuesta de pago',
        'create_run' => 'Crear remesa',
        'show' => 'Ver',
        'release' => 'Aprobar',
        'export' => 'Fichero SEPA',
        'cancel' => 'Anular',
        'adjust' => 'Ajustar importe',
        'remove_item' => 'Eliminar posición',
    ],

    'column' => [
        'label' => 'Denominación',
        'kind' => 'Tipo',
        'account' => 'Cuenta bancaria',
        'execution_date' => 'Ejecución',
        'positions' => 'Posiciones',
        'total' => 'Total',
        'status' => 'Estado',
        'creditor' => 'Beneficiario',
        'invoice_number' => 'Factura',
        'due_date' => 'Vencimiento',
        'execute_on' => 'Pagar el',
        'gross' => 'Importe facturado',
        'amount' => 'Importe a pagar',
        'note' => 'Aviso',
        'reference' => 'Concepto',
        'deduction' => 'Deducción',
    ],

    'status' => [
        'draft' => 'Borrador',
        'released' => 'aprobada',
        'exported' => 'exportada',
        'cancelled' => 'anulada',
    ],

    'iban_confirmed' => 'Se confirmó el IBAN divergente — la posición ya es pagadera.',

    'blocked' => [
        'missing_iban' => 'Falta el IBAN',
        'zero_amount' => 'Importe 0',
        'iban_differs' => 'El IBAN difiere de los datos maestros',
    ],

    'error' => [
        'no_iban_deviation' => 'El IBAN de la factura no difiere (ya) de los datos maestros del proveedor.',
        'no_positions' => 'La remesa no contiene posiciones.',
        'not_draft' => 'La remesa ya no es un borrador.',
        'not_released' => 'La remesa no está aprobada.',
        'four_eyes' => 'Principio de los cuatro ojos: quien preparó la remesa no puede aprobarla por sí mismo.',
        'exported_final' => 'Una remesa exportada ya no se anula.',
        'invalid_amount' => 'El importe a pagar debe ser mayor que 0 y no puede superar el importe facturado.',
        'reason_required' => 'Un importe reducido requiere un motivo.',
        'zero_amount' => 'El importe debe ser mayor que 0.',
        'account_without_iban' => 'La cuenta bancaria elegida no tiene IBAN registrado.',
        'missing_creditor_id' => 'No hay identificador de acreedor registrado (ajuste finance.sepa_creditor_id).',
        'mandate_unusable' => 'El mandato está revocado o lleva más de 36 meses sin uso.',
        'item_without_mandate' => 'Una posición de adeudo sin mandato no puede exportarse.',
        'unavailable' => 'La exportación SEPA no está habilitada en esta instalación. Activación mediante :contact.',
        'invoice_not_collectable' => 'La factura no pertenece al cliente del mandato o ya no está abierta.',
    ],

    'mandate' => [
        'title' => 'Mandatos SEPA',
        'subtitle' => 'Mandatos de adeudo de los clientes',
        'empty' => 'Todavía no hay ningún mandato registrado.',
        'created' => 'Mandato creado.',
        'revoked' => 'Mandato revocado.',
        'confirm_revoke' => '¿Revocar el mandato? A partir de entonces el adeudo ya no está permitido.',
        'not_usable' => 'no adeudable',
        'reference_hint' => 'Único por acreedor; aparece en el extracto del cliente.',

        'action' => [
            'create' => 'Registrar mandato',
            'revoke' => 'Revocar',
        ],

        'column' => [
            'reference' => 'Referencia del mandato',
            'customer' => 'Cliente',
            'kind' => 'Tipo',
            'signed_on' => 'Firmado el',
            'last_collected_on' => 'Último adeudo',
            'status' => 'Estado',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'account_holder' => 'Titular de la cuenta',
            'note' => 'Nota',
        ],
    ],
    // Einbehalte an Eingangsrechnungen (MVP-953).
    'retention' => [
        'title' => 'Retenciones',
        'empty' => 'Sin retenciones. Una retención reduce el pago hasta que se libera.',
        'add' => 'Añadir retención',
        'release' => 'Liberar',
        'confirm_release' => '¿Liberar la retención? Después se ofrecerá para su pago en la propuesta de pagos.',
        'due' => 'vencida',
        'in_run' => 'en remesa de pago',
        'select' => 'Pagar retención',
        'retained' => 'Retención :amount €',
        'payable_title' => 'Retenciones liberadas',
        'deduction' => 'Retención',
        'reference' => 'Retención factura :number',
        'field' => [
            'kind' => 'Tipo',
            'percent' => 'Porcentaje',
            'amount' => 'Importe',
            'due_on' => 'Liberación el',
            'released_on' => 'Liberada el',
            'note' => 'Nota',
        ],
        'error' => [
            'in_run' => 'La factura ya está en una remesa de pago.',
            'amount' => 'Indique un porcentaje o importe mayor que 0.',
            'exceeds' => 'Las retenciones superan el importe de la factura.',
            'not_open' => 'Solo se pueden liberar retenciones abiertas.',
            'locked' => 'La retención ya no se puede eliminar.',
        ],
        'flash' => [
            'saved' => 'Retención añadida.',
            'released' => 'Retención liberada.',
            'removed' => 'Retención eliminada.',
        ],
        'notification' => [
            'title' => 'Retención :supplier (:number)',
            'due' => 'Liberación de la retención el :date.',
            'overdue' => 'La liberación de la retención vencía el :date.',
        ],
    ],
];
