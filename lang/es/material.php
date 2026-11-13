<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Material ↔ Artikel (MVP-904).
    'article' => [
        'label' => 'Artículo',
        'none' => '— sin artículo —',
        'hint' => 'A través del artículo, el informe de proveedores asigna el consumo a un proveedor.',
        'link' => 'Asignar artículos',
        'link_hint' => 'Asignar materiales sin artículo mediante el mismo SKU o número de artículo; las coincidencias ambiguas quedan abiertas.',
        'linked' => ':count materiales asignados a un artículo.',
    ],
    'stock' => [
        'insufficient' => 'En el almacén «:warehouse» solo hay :available :unit disponibles. Para una salida a existencias negativas, marque «Permitir stock negativo».',
        'negative_forbidden' => 'Solo quien tenga el permiso «Autorizar existencias negativas» puede registrar una salida a existencias negativas.',
        'serial_required' => 'El artículo requiere número de serie. Retírelo desde el almacén con su número de serie o registre el consumo sin ficha de material.',
        'booking_failed' => 'No se pudo registrar la salida de almacén: :reason',
        'return_failed' => 'No se pudo registrar la devolución al almacén: :reason',
        'usage_gone' => 'El consumo de material de esta solicitud ya no existe.',
        'allow_negative_hint' => 'Solo es necesario si un artículo con gestión de existencias no tiene suficientes existencias en el almacén predeterminado.',
        'notice' => ':status · almacén «:warehouse»',
        'current' => 'Existencias: :notice',
    ],
];
