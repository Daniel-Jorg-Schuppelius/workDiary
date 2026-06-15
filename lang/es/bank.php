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
        'file' => 'Archivo',
        'account_optional' => 'Cuenta bancaria (opcional, de lo contrario asignación automática por IBAN)',
        'flash' => [
            'imported' => ':count movimientos importados.',
        ],
        'error' => [
            'empty' => 'El extracto no contiene movimientos.',
            'empty_file' => 'El archivo está vacío.',
            'duplicate_file' => 'Este archivo ya se ha importado (duplicado).',
            'unavailable' => 'La importación bancaria no está disponible en esta instalación (falta el paquete php-financial-formats).',
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
