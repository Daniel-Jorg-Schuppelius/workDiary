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

    'blocked' => [
        'missing_iban' => 'Falta el IBAN',
        'zero_amount' => 'Importe 0',
    ],

    'error' => [
        'no_positions' => 'La remesa no contiene posiciones.',
        'not_draft' => 'La remesa ya no es un borrador.',
        'not_released' => 'La remesa no está aprobada.',
        'exported_final' => 'Una remesa exportada ya no se anula.',
        'invalid_amount' => 'El importe a pagar debe ser mayor que 0 y no puede superar el importe facturado.',
        'reason_required' => 'Un importe reducido requiere un motivo.',
        'zero_amount' => 'El importe debe ser mayor que 0.',
        'account_without_iban' => 'La cuenta bancaria elegida no tiene IBAN registrado.',
        'missing_creditor_id' => 'No hay identificador de acreedor registrado (ajuste finance.sepa_creditor_id).',
        'mandate_unusable' => 'El mandato está revocado o lleva más de 36 meses sin uso.',
        'item_without_mandate' => 'Una posición de adeudo sin mandato no puede exportarse.',
        'unavailable' => 'La exportación SEPA no está habilitada en esta instalación. Activación mediante :contact.',
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
];
