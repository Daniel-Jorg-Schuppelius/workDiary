<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'receipt' => [
        'no_vendor' => 'Sin proveedor',
        'link_title' => 'Comprobante contable',
        'link' => 'Vincular',
        'unlink' => 'Quitar vínculo',
        'unlink_confirm' => '¿Quitar el vínculo con el comprobante contable? El gasto volverá a contar como coste propio.',
        'suggestions_hint' => 'Comprobantes con el mismo importe dentro de la ventana temporal. Vincular confirma que es la misma operación — el gasto deja entonces de contar dos veces.',
        'no_suggestions' => 'No se encontró un comprobante coincidente',
        'no_suggestions_hint' => 'Sin vínculo, el gasto se muestra por separado como gasto interno.',
        'no_provider' => 'Sin contabilidad conectada',
        'no_provider_hint' => 'Sin un sistema contable conectado no hay ni sugerencias de comprobantes ni transferencia: el gasto se muestra por separado como gasto interno.',
        'linked' => 'Comprobante :number vinculado.',
        'unlinked' => 'Vínculo eliminado.',
        'title' => 'Archivo del recibo',
        'hint' => 'Adjunta el recibo al gasto — sin él no se puede verificar ni trasladar después a contabilidad.',
    ],
    'title' => [
        'index' => 'Gastos',
        'create' => 'Registrar gasto',
        'edit' => 'Editar gasto',
        'inbox' => 'Aprobación de gastos',
        'category_index' => 'Categorías de gasto',
        'category_create' => 'Crear categoría de gasto',
        'category_edit' => 'Editar categoría de gasto',
    ],
    'intro' => [
        'category' => 'Las categorías de gasto agrupan los justificantes (p. ej. comidas, alojamiento, atenciones) y definen valores predeterminados como el tipo impositivo, la obligación de adjuntar un justificante y si el gasto es por defecto refacturable al cliente. El icono y el color definen la apariencia en listas e informes.',
    ],
    'field' => [
        'label' => 'Etiqueta',
        'slug' => 'Slug',
        'icon' => 'Icono (material symbol)',
        'color' => 'Color',
        'description' => 'Descripción',
        'sort' => 'Orden',
        'is_active' => 'Activo',
        'default_tax_rate' => 'Tipo impositivo (predeterminado, %)',
        'requires_receipt' => 'Justificante obligatorio',
        'default_billable' => 'Refacturable al cliente por defecto',
        'date' => 'Fecha del justificante',
        'category' => 'Categoría',
        'vendor' => 'Proveedor',
        'amount_gross' => 'Importe bruto',
        'amount_net' => 'Importe neto',
        'tax_rate' => 'Tipo impositivo (%)',
        'tax_amount' => 'Importe de impuesto',
        'currency' => 'Moneda',
        'payment_method' => 'Método de pago',
        'project' => 'Proyecto',
        'customer' => 'Cliente',
        'task' => 'Tarea',
        'billable' => 'Refacturable al cliente',
        'notes' => 'Notas',
        'status' => 'Estado',
        'attachments' => 'Justificantes',
        'reimbursement_reference' => 'Referencia de reembolso',
        'reject_reason' => 'Motivo de rechazo',
        'decided_at' => 'Decidido el',
        'reimbursed_at' => 'Reembolsado el',
    ],
    'action' => [
        'create_category' => 'Crear categoría',
        'create' => 'Registrar gasto',
        'submit' => 'Enviar para aprobación',
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'cancel' => 'Cancelar',
        'reimburse' => 'Marcar como reembolsado',
        'export' => 'Exportar CSV',
    ],
    'help' => [
        'color' => 'Define el color de acento para el icono, la insignia y los resaltados en las listas.',
        'gross_first' => 'Introduce el importe bruto del justificante. El importe neto y el impuesto se calculan automáticamente.',
        'requires_receipt' => 'Si está activo, se requiere al menos un justificante (foto/PDF) al registrar.',
    ],
    'empty' => [
        'categories' => 'Aún no hay categorías de gasto.',
        'expenses' => 'Aún no hay gastos registrados.',
    ],
    'confirm' => [
        'delete_category' => '¿Eliminar realmente esta categoría de gasto?',
        'delete_expense' => '¿Eliminar realmente este gasto?',
    ],
];
