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
    'description' => 'Invoice :number',
    'page' => [
        'title' => 'Invoice :number',
        'paid' => 'This invoice has been paid. Thank you!',
        'processing' => 'Thank you! The payment is being processed; once the payment provider confirms it, the invoice is settled.',
        'not_payable' => 'This invoice cannot be paid online. Please contact the issuer.',
        'unavailable' => 'Online payment is not possible at the moment. Please try again later or pay by bank transfer.',
    ],
    'pdf' => [
        'title' => 'Pay online',
        'hint' => 'Scan the QR code or open the link:',
    ],
    'portal' => [
        'pay' => 'Pay online',
    ],
];
