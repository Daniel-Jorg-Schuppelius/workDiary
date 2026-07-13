<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bank.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'menu' => 'Conciliación de pagos',
        'index' => 'Extractos bancarios',
        'statement' => 'Extracto bancario',
        'transactions' => 'Movimientos bancarios',
        'suggestions' => 'Sugerencias de asignación',
        'allocations' => 'Asignaciones confirmadas',
        'accounts' => 'Cuentas bancarias',
        'account' => 'Cuenta bancaria',
    ],
    'subtitle' => [
        'index' => 'Importar extractos bancarios (CAMT.053/MT940), revisar los movimientos y asignarlos a facturas o gastos abiertos.',
        'accounts' => 'Cuentas bancarias propias de la organización para la asignación automática de los extractos entrantes.',
    ],
    'field' => [
        'format' => 'Formato',
        'imported_at' => 'Importado el',
        'imported_by' => 'Importado por',
        'account' => 'Cuenta bancaria',
        'period' => 'Período',
        'opening_balance' => 'Saldo inicial',
        'closing_balance' => 'Saldo final',
        'balance_check' => 'Cadena de saldos',
        'tx_count' => 'Movimientos',
        'open' => 'Abierto',
        'matched' => 'Asignado',
        'booking_date' => 'Contabilización',
        'valuta_date' => 'Fecha valor',
        'amount' => 'Importe',
        'direction' => 'Sentido',
        'currency' => 'Moneda',
        'counterparty' => 'Contraparte',
        'purpose' => 'Concepto',
        'reference' => 'Referencia',
        'status' => 'Estado',
        'score' => 'Puntuación',
        'kind' => 'Tipo',
        'note' => 'Nota',
        'label' => 'Etiqueta',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Titular de la cuenta',
        'datev_account_no' => 'N.º de cuenta DATEV',
        'is_active' => 'Activo',
    ],
    'reason' => [
        'reference' => 'Número de factura',
        'amount' => 'El importe coincide',
        'skonto' => 'Descuento por pronto pago',
        'iban' => 'Coincidencia de IBAN',
        'date' => 'Proximidad de fecha',
        'foreign_currency' => 'Moneda extranjera – revisar manualmente',
    ],
    'action' => [
        'import' => 'Importar archivo bancario',
        'upload' => 'Importar',
        'show' => 'Mostrar',
        'download' => 'Descargar archivo original',
        'confirm' => 'Confirmar',
        'confirm_selected' => 'Confirmar selección',
        'ignore' => 'Apartar',
        'unassignable' => 'No asignable',
        'unmatch' => 'Deshacer asignación',
        'manual' => 'Asignar manualmente',
        'new_account' => 'Añadir cuenta bancaria',
        'edit_account' => 'Editar cuenta bancaria',
        'delete_account' => 'Eliminar cuenta bancaria',
        'manage_accounts' => 'Gestionar cuentas bancarias',
    ],
    'import' => [
        'dialog_title' => 'Importar archivo bancario',
        'dialog_hint' => 'CAMT.053 (XML) o MT940. La importación solo crea los movimientos en el área de revisión y no cambia ningún estado de factura o gasto.',
        'format_hint' => 'Formatos admitidos: CAMT.053, MT940, OFX, QIF, QXF y PAIN.001/008 (órdenes de pago como movimientos anunciados). La detección se basa en el contenido, no en la extensión del archivo.',
        'file' => 'Archivo',
        'account_optional' => 'Cuenta bancaria (opcional, de lo contrario asignación automática por IBAN)',
        'flash' => [
            'imported' => ':count movimientos importados.',
        ],
        'error' => [
            'empty' => 'El extracto no contiene movimientos.',
            'empty_file' => 'El archivo está vacío.',
            'duplicate_file' => 'Este archivo ya se ha importado (duplicado).',
            'unavailable' => 'La importación bancaria es un módulo adicional opcional y de pago, no activado en esta instalación. Su activación es posible bajo petición en :contact.',
        ],
    ],
    'reconcile' => [
        'flash' => [
            'confirmed' => 'Asignación confirmada.',
            'ignored' => 'Movimiento apartado.',
            'unassignable' => 'Movimiento marcado como no asignable.',
            'unmatched' => 'Asignación deshecha.',
        ],
        'error' => [
            'no_allocations' => 'No se indicó ninguna asignación.',
            'target_not_found' => 'No se encontró el destino de la asignación.',
        ],
    ],
    // Sammelbuchungs-Auflösung je TransactionDetail (Toolkit-Folgepaket 2).
    'split' => [
        'title' => 'Desglosar asiento colectivo',
        'return_title' => 'Devolución colectiva de adeudo — procesar por transacción individual',
        'target' => 'Partida',
        'target_placeholder' => '— Seleccionar partida —',
        'no_match' => 'No se encontró ninguna partida',
    ],
    // Lastschrift-Rückläufer-Workflow (MVP-334).
    'return' => [
        'badge' => 'Devolución',
        'title' => 'Procesar devolución de adeudo',
        'action' => 'Compensar',
        'reason_placeholder' => 'Motivo (p. ej. AC04)',
        'flash' => [
            'processed' => 'Devolución procesada — asignación original compensada, partida reabierta.',
        ],
        'error' => [
            'same_transaction' => 'La asignación pertenece al propio movimiento de devolución.',
            'not_compensatable' => 'Esta asignación no puede compensarse.',
            'already_compensated' => 'Esta asignación ya fue compensada.',
        ],
        'reason' => [
            'amount' => 'Importe coincide',
            'reference' => 'Referencia coincidente',
            'mandate' => 'Referencia de mandato',
            'date' => 'Proximidad de fecha',
        ],
    ],
    'account' => [
        'flash' => [
            'created' => 'Cuenta bancaria creada.',
            'updated' => 'Cuenta bancaria actualizada.',
            'deleted' => 'Cuenta bancaria eliminada.',
        ],
        'error' => [
            'duplicate_iban' => 'Ya existe una cuenta bancaria para este IBAN.',
        ],
    ],
    'empty' => [
        'statements' => 'Aún no se han importado extractos bancarios.',
        'transactions' => 'No hay movimientos en este extracto.',
        'suggestions' => 'Sin sugerencias: asigna manualmente o aparta.',
        'accounts' => 'Aún no se han creado cuentas bancarias.',
    ],
];
