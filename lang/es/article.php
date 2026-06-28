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
        'flash' => ['preferred_set' => 'Fuente de suministro preferida establecida.'],
    ],
    'no_options' => 'No hay opciones definidas.',
    'no_variants' => 'No hay variantes creadas.',
    'sku_auto_hint' => 'se asigna automáticamente',

    'action' => [
        'create' => 'Crear artículo',
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
