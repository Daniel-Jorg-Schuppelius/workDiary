<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Portal de clientes «mis suscripciones» (función 152): inventario sin precios, compras ni comprobantes.
return [
    'title' => 'Mis suscripciones',
    'menu' => 'Suscripciones',
    'subtitle' => 'Sus suscripciones y licencias — incluidas las de sus clientes finales. Los precios e importes figuran en sus facturas.',
    'field' => [
        'product' => 'Denominación / producto',
        'holder' => 'Titular',
        'quantity' => 'Cantidad',
        'term' => 'Vigencia',
        'interval' => 'Intervalo',
        'renewal' => 'Renovación',
        'next_period' => 'Próximo periodo',
        'status' => 'Estado',
        'kind' => 'Tipo',
        'period' => 'Periodo',
    ],
    'holder' => [
        'end_customer' => 'Cliente final',
    ],
    'term' => [
        'since' => 'desde el :date',
        'range' => ':from – :to',
        'running' => 'en curso',
    ],
    'interval' => [
        'yearly' => 'anual',
        'monthly' => 'mensual',
    ],
    'next_period' => [
        'none' => 'ninguno más',
    ],
    'period_status' => [
        'open' => 'abierto',
        'billed' => 'facturado',
        'partial' => 'parcialmente facturado',
        'waived' => 'no facturado',
        'disputed' => 'en revisión',
    ],
    'periods' => [
        'title' => 'Periodos de facturación',
        'hint' => 'Los periodos se derivan del inicio, la vigencia y el intervalo; «facturado» significa que ya dispone de la factura correspondiente.',
        'empty' => 'Aún no hay periodos planificados.',
    ],
    'empty' => 'No hay suscripciones registradas.',
    'back' => 'Volver al resumen',
    'show' => 'Detalles',
];
