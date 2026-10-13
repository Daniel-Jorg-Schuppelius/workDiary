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
        'sevdesk_cancel_not_transferred' => 'La factura :number no está en sevDesk; la anulación no se puede crear allí. Asigne la serie «Anulación» a WorkDiary para numerarla localmente.',
        'sevdesk_outcome_unclear' => 'Resultado de la entrega a sevDesk incierto (tiempo de espera agotado tras el envío): no reintentar a ciegas; la próxima ejecución concilia mediante el marcador de origen.',
    ],
    'sevdesk' => [
        'introduction' => 'Les facturamos como sigue nuestras entregas y servicios del período :from – :to.',
        'tax_text' => 'IVA :rate %',
    ],
    'invoice_status' => [
        '50' => 'Factura recurrente inactiva',
        '100' => 'Borrador',
        '200' => 'abierta',
        '750' => 'pagada parcialmente',
        '1000' => 'pagada',
    ],
];
