<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : article.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Artículos',
    'subtitle' => 'Maestro de artículos canónico del inquilino (productos, material, servicios).',
    'empty' => 'Aún no se han creado artículos.',
    'variants' => 'Variantes',
    'options' => 'Opciones',
    'units' => 'Unidades',
    'external_mappings' => 'Asignaciones externas',
    'supplies' => [
        'title' => 'Fuentes de suministro',
        'supplier' => 'Proveedor',
        'sku' => 'N.º art. proveedor',
        'price' => 'Precio de compra',
        'lead_time' => 'Plazo de entrega',
        'moq' => 'Cant. mín.',
        'days' => 'días',
        'preferred' => 'Preferido',
        'recommended' => 'Recomendado',
        'set_preferred' => 'Marcar como preferido',
        'flash' => ['preferred_set' => 'Fuente de suministro preferida establecida.', 'datanorm_empty' => 'No hay artículos exportables (activos y vendibles).'],
    ],
    'no_options' => 'No hay opciones definidas.',
    'no_variants' => 'No hay variantes creadas.',
    'sku_auto_hint' => 'se asigna automáticamente',

    'datanorm_oversized' => ':count número de artículo supera los 15 caracteres y queda fuera de la exportación DATANORM.|:count números de artículo superan los 15 caracteres y quedan fuera de la exportación DATANORM.',

    'discount_group' => [
        'title' => 'Grupos de descuento de venta',
        'hint' => 'Condiciones estándar de la organización para exportaciones DATANORM con precios de lista: los destinatarios calculan lista − descuento = neto. Los precios por cliente van por el DATPREIS B2B.',
        'empty' => 'Aún no hay grupos de descuento.',
        'confirm_delete' => '¿Eliminar este grupo de descuento? Se quitarán las asignaciones de artículos.',
        'kind' => ['discount' => 'Descuento (%)', 'factor' => 'Factor', 'surcharge' => 'Recargo (%)'],
        'col' => ['code' => 'Código', 'kind' => 'Tipo', 'value' => 'Valor', 'label' => 'Denominación', 'articles' => 'Artículos'],
        'action' => ['add' => 'Crear', 'delete' => 'Eliminar'],
        'flash' => ['created' => 'Grupo de descuento creado.', 'deleted' => 'Grupo de descuento eliminado.', 'override_saved' => 'Excepción de cliente guardada.', 'override_deleted' => 'Excepción de cliente eliminada.'],
        'override' => [
            'title' => 'Excepciones por cliente',
            'hint' => 'Tasas específicas por cliente y grupo de descuento — se aplican en el DATPREIS B2B del cliente; un custom_price de artículo prevalece.',
            'customer' => 'Cliente',
            'empty' => 'Aún no hay excepciones por cliente.',
        ],
    ],

    'action' => [
        'create' => 'Crear artículo',
        'export_datanorm' => 'Exportación DATANORM',
        'export_datanorm_v5_list' => 'DATANORM 5 — PV como precio de lista',
        'export_datanorm_v5_net' => 'DATANORM 5 — PV como precio neto',
        'export_datanorm_v4_list' => 'DATANORM 4 — PV como precio de lista',
        'export_datpreis_title' => 'Archivo de precios (DATPREIS)',
        'export_datpreis_v5' => 'DATPREIS 5 — PV actuales',
        'export_datpreis_v4' => 'DATPREIS 4 — PV actuales',
        'export_datpreis_since' => 'DATPREIS 5 — cambios de los últimos 30 días',
        'export_datpreis_custom' => 'DATPREIS desde fecha',
        'edit' => 'Editar artículo',
        'retire' => 'Retirar',
        'add_option' => 'Añadir opción',
        'add_value' => 'Valor',
        'add_variant' => 'Crear variante',
        'add_unit' => 'Añadir unidad',
    ],

    'field' => [
        'sku' => 'Número de artículo (SKU)',
        'type' => 'Tipo de artículo',
        'status' => 'Estado',
        'base_unit' => 'Unidad base',
        'category' => 'Grupo de mercancías',
        'category_hint' => 'Para informes y la exportación DATANORM (archivo WRG).',
        'subcategory' => 'Subgrupo de mercancías',
        'sales_discount_group' => 'Grupo de descuento de venta',
        'sales_discount_group_hint' => 'Para exportaciones DATANORM con precios de lista (archivo RAB).',
        'assembly_minutes' => 'Tiempo de montaje (minutos por unidad)',
        'assembly_minutes_hint' => 'Tiempo de trabajo calculado; se rellena desde registros ARBA en la adopción DATANORM.',
        'gtin' => 'GTIN',
        'default_purchase_price' => 'Precio de compra (predeterminado)',
        'default_sale_price' => 'Precio de venta (predeterminado)',
        'currency' => 'Moneda',
        'code' => 'Código',
        'label' => 'Etiqueta',
        'option_name' => 'Nombre de la opción',
        'combination' => 'Combinación',
        'sale_price' => 'Precio de venta',
        'unit_kind' => 'Tipo',
        'factor_to_base' => 'Factor a la unidad base',
        'external_id' => 'ID externo',
        'sync_status' => 'Estado de sincronización',
    ],

    'group' => [
        'pricing' => 'Precios',
        'flags' => 'Propiedades',
    ],

    'flag' => [
        'stockable' => 'Almacenable',
        'purchasable' => 'Comprable',
        'sellable' => 'Vendible',
        'manufacturable' => 'Fabricable',
        'batch_required' => 'Trazado por lote',
        'serial_required' => 'Trazado por número de serie',
        'shelf_life_required' => 'Caducidad requerida',
    ],

    'type' => [
        'raw' => 'Materia prima',
        'consumable' => 'Consumible',
        'merchandise' => 'Mercancía',
        'semifinished' => 'Producto semiacabado',
        'finished' => 'Producto terminado',
        'service' => 'Servicio',
    ],

    'status' => [
        'draft' => 'Borrador',
        'active' => 'Activo',
        'retired' => 'Retirado',
    ],

    'unit_kind' => [
        'base' => 'Base',
        'purchase' => 'Compra',
        'sale' => 'Venta',
        'packaging' => 'Embalaje',
    ],

    'confirm' => [
        'retire' => '¿Retirar realmente este artículo? Las variantes también se retirarán.',
        'delete' => '¿Eliminar definitivamente este artículo? Solo se pueden eliminar borradores sin referencias.',
    ],

    'flash' => [
        'created' => 'Artículo creado.',
        'updated' => 'Artículo actualizado.',
        'deleted' => 'Artículo eliminado.',
        'retired' => 'Artículo retirado.',
        'delete_blocked' => 'No se puede eliminar el artículo: solo los borradores sin referencias son eliminables. Retírelo en su lugar.',
        'option_added' => 'Opción añadida.',
        'value_added' => 'Valor de opción añadido.',
        'unit_added' => 'Unidad añadida.',
        'variant_added' => 'Variante creada.',
        'variant_retired' => 'Variante retirada.',
    ],
];
