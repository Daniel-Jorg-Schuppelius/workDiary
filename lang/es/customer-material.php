<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'panel_title' => 'Costes de material y beneficio',
    'add_title' => 'Asignar costes de material',
    'source' => 'Origen del coste',
    'source_hint' => 'Seleccione un documento de compra de Lexoffice o introduzca un importe libre.',
    'voucher' => 'Documento de compra',
    'voucher_hint' => 'Opcional: se admite un importe parcial; un documento puede repartirse entre varios clientes.',
    'manual_amount' => '— Importe libre —',
    'description' => 'Descripción',
    'description_hint' => 'Obligatorio sin documento: denomina los costes de material.',
    'allocation' => 'Asignación',
    'amount' => 'Importe',
    'amount_hint' => 'Importe (parcial) asignado al cliente.',
    'date' => 'Fecha',
    'project' => 'Proyecto',
    'project_hint' => 'Opcional: para una asignación más detallada.',
    'no_project' => '— Sin proyecto —',
    'source_lexoffice' => 'Documento de Lexoffice',
    'revenue' => 'Ingresos (facturados)',
    'material_cost' => 'Costes de material',
    'profit' => 'Beneficio (calc.)',
    'margin' => 'margen',
    'range_hint' => 'Valores del periodo seleccionado (:range).',
    'double_count_hint' => 'Vista de gestión (sin gastos generales). Asigne el material mediante un documento de compra O mediante una salida de almacén, no ambos para la misma mercancía.',
    'empty_hint' => 'Todavía no hay costes de material asignados. Use «Asignar costes de material» para asignar documentos o importes libres al cliente y mostrar el beneficio.',
    'confirm_delete' => '¿Seguro que desea eliminar esta asignación de costes de material?',
    'delete' => 'Eliminar',
    'flash_saved' => 'Costes de material asignados.',
    'flash_deleted' => 'Asignación de costes de material eliminada.',
    'error_description_required' => 'Indique una descripción si no se selecciona ningún documento.',
    'error_voucher_not_purchase' => 'El documento seleccionado no es un documento de compra.',
    'error_amount_over_voucher' => 'El importe supera el total del documento.',
    'error_project_foreign' => 'El proyecto no pertenece a este cliente.',
    'stock_title' => 'Retirar del almacén',
    'stock_issue' => 'Retirar y contabilizar',
    'stock_source' => 'Salida de almacén',
    'stock_hint' => 'Valoración a precio medio ponderado; la salida reduce las existencias y se contabiliza como coste de material.',
    'article' => 'Artículo',
    'warehouse' => 'Almacén',
    'qty' => 'Cantidad',
    'qty_hint' => 'En unidad base.',
    'choose' => '— Seleccione —',
    'source_stock' => 'Almacén',
    'stock_item' => 'Artículo de almacén',
    'flash_stock_issued' => 'Salida de almacén contabilizada y asignada como coste de material.',
    'book_to_customer' => 'Cliente de los costes de material',
    'no_customer' => '— Sin cliente —',
];
