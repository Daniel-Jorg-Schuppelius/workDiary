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
    'error' => [
        'sevdesk_not_configured' => 'sevDesk no está configurado para esta organización (falta el token de API).',
        'sevdesk_outcome_unclear' => 'Resultado de la entrega a sevDesk incierto (tiempo de espera agotado tras el envío): no reintentar a ciegas; la próxima ejecución concilia mediante el marcador de origen.',
    ],
    'sevdesk' => [
        'introduction' => 'Les facturamos como sigue nuestras entregas y servicios del período :from – :to.',
        'tax_text' => 'IVA :rate %',
    ],
];
