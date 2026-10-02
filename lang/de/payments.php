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
    'description' => 'Rechnung :number',
    'page' => [
        'title' => 'Rechnung :number',
        'paid' => 'Diese Rechnung ist bezahlt. Vielen Dank!',
        'processing' => 'Vielen Dank! Die Zahlung wird verarbeitet; sobald der Zahlungsanbieter sie bestätigt, ist die Rechnung beglichen.',
        'not_payable' => 'Diese Rechnung kann nicht online bezahlt werden. Bitte wenden Sie sich an den Rechnungssteller.',
        'unavailable' => 'Die Online-Zahlung ist gerade nicht möglich. Bitte versuchen Sie es später erneut oder überweisen Sie den Betrag.',
    ],
    'pdf' => [
        'title' => 'Online bezahlen',
        'hint' => 'QR-Code scannen oder Link öffnen:',
    ],
    'portal' => [
        'pay' => 'Online bezahlen',
    ],
];
