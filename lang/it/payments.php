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
    'description' => 'Fattura :number',
    'page' => [
        'title' => 'Fattura :number',
        'paid' => 'Questa fattura è stata pagata. Grazie!',
        'processing' => 'Grazie! Il pagamento è in elaborazione; non appena il fornitore di pagamento lo conferma, la fattura risulta saldata.',
        'not_payable' => 'Questa fattura non può essere pagata online. La preghiamo di contattare l\'emittente.',
        'unavailable' => 'Il pagamento online non è al momento possibile. Riprovi più tardi o paghi tramite bonifico.',
    ],
    'pdf' => [
        'title' => 'Paga online',
        'hint' => 'Scansioni il codice QR o apra il link:',
    ],
    'portal' => [
        'pay' => 'Paga online',
    ],
];
