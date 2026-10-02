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
    'description' => 'Facture :number',
    'page' => [
        'title' => 'Facture :number',
        'paid' => 'Cette facture est payée. Merci !',
        'processing' => 'Merci ! Le paiement est en cours de traitement ; dès que le prestataire de paiement le confirme, la facture est réglée.',
        'not_payable' => 'Cette facture ne peut pas être payée en ligne. Veuillez contacter l\'émetteur.',
        'unavailable' => 'Le paiement en ligne n\'est pas possible pour le moment. Veuillez réessayer plus tard ou payer par virement.',
    ],
    'pdf' => [
        'title' => 'Payer en ligne',
        'hint' => 'Scannez le QR code ou ouvrez le lien :',
    ],
    'portal' => [
        'pay' => 'Payer en ligne',
    ],
];
