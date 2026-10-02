<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'easybill' => [
        'introduction' => 'Les facturamos como sigue nuestras entregas y servicios del periodo :from – :to.',
        'unit_hour' => 'h',
        'unit_piece' => 'uds.',
    ],
    'error' => [
        'easybill_not_configured' => 'easybill no está configurado para esta organización (falta la clave de API).',
        'easybill_outcome_unclear' => 'Resultado de la transferencia a easybill incierto (tiempo de espera tras el envío): no reintentar a ciegas; la siguiente ejecución concilia mediante el marcador de origen.',
    ],
];
