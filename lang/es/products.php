<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : products.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370).
return [
    'title' => [
        'index' => 'Productos',
        'subtitle' => 'Nivel de tipo fabricante + modelo: agrupa artículos y activos del mismo producto.',
        'create' => 'Crear producto',
        'edit' => 'Editar producto',
        'empty' => 'Aún no hay productos.',
        'empty_search' => 'No se encontraron productos para «:q».',
    ],
    'field' => [
        'basics' => 'Datos maestros',
        'manufacturer' => 'Fabricante',
        'model' => 'Modelo',
        'name' => 'Nombre visible',
        'name_placeholder' => 'Fabricante modelo',
        'name_help' => 'Dejar vacío para «fabricante modelo».',
        'product_group' => 'Grupo de productos',
        'no_group' => '— ninguno —',
        'articles' => 'Artículos',
        'assets' => 'Activos',
        'status' => 'Estado',
        'notes' => 'Notas',
        'product' => 'Producto',
        'no_product' => '— sin producto —',
        'product_help' => 'Asignación de tipo (fabricante modelo); rellena fabricante/modelo.',
    ],
    'action' => [
        'create' => 'Crear producto',
        'save' => 'Guardar',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'delete_confirm' => '¿Eliminar realmente el producto? Los artículos y activos se conservan y solo pierden la asignación de tipo.',
    ],
    'flash' => [
        'created' => 'Producto creado.',
        'updated' => 'Producto actualizado.',
        'deleted' => 'Producto eliminado.',
    ],
];
