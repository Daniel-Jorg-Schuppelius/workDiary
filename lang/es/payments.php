<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : payments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Online-Zahlung von Rechnungen (MVP-1067).
return [
    'description' => 'Factura :number',
    'page' => [
        'title' => 'Factura :number',
        'paid' => 'Esta factura está pagada. ¡Gracias!',
        'processing' => '¡Gracias! El pago se está procesando; en cuanto el proveedor de pagos lo confirme, la factura quedará saldada.',
        'not_payable' => 'Esta factura no se puede pagar en línea. Póngase en contacto con el emisor.',
        'unavailable' => 'El pago en línea no es posible en este momento. Inténtelo más tarde o pague por transferencia.',
    ],
    'pdf' => [
        'title' => 'Pagar en línea',
        'hint' => 'Escanee el código QR o abra el enlace:',
    ],
    'portal' => [
        'pay' => 'Pagar en línea',
    ],
];
