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
];
