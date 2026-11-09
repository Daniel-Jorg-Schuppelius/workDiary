<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → sevDesk (Feature 163, MVP-1507).

return [
    'label' => 'sevDesk',
    'amount_differs' => 'In sevDesk esiste già il documento :number con un importo diverso. Verificarlo lì e poi riavviare il trasferimento.',
    'candidate' => 'In sevDesk c’è probabilmente già lo stesso documento (:voucher), ma senza numero di fattura non è certo. Collegarlo o far creare un nuovo documento.',
    'no_party' => 'La ricezione non è ancora assegnata a nessuna parte.',
    'contact_missing' => 'Il contatto :party non è stato né trovato in modo univoco né creato in sevDesk. Collegare il contatto in sevDesk e poi riavviare il trasferimento.',
    'no_account' => 'Nelle impostazioni di sevDesk non è indicato alcun conto contabile per questa direzione.',
    'currency' => 'sevDesk riceve dalla ricezione fatture solo documenti in euro. Registrare il documento in sevDesk; il trasferimento successivo lo collega tramite il numero di fattura.',
    'rates' => 'Le aliquote non sono compatibili con sevDesk (0, 7 o 19 %) o i totali sono incompleti. Registrare il documento in sevDesk; il trasferimento successivo lo collega tramite il numero di fattura.',
    'tax_case' => 'Inversione contabile, casi intracomunitari ed esportazioni li registra la contabilità direttamente in sevDesk. Registrare lì il documento; il trasferimento successivo lo collega tramite il numero di fattura.',
    'xml_requires_update' => 'sevDesk accetta le XRechnung solo dalla versione contabile 2.0 e alla ricezione non è allegato alcun PDF.',
    'settings' => [
        'transfer' => 'Trasferire la ricezione fatture a sevDesk',
        'transfer_help' => 'Le ricezioni assegnate vanno a sevDesk come bozza con l’originale. Se lì esiste già un documento con lo stesso numero, viene solo collegato.',
        'incoming_account' => 'Conto contabile per documenti in entrata (ID)',
        'outgoing_account' => 'Conto contabile per documenti in uscita (ID)',
        'account_help' => 'ID del conto nel piano dei conti di sevDesk: AccountDatev con la versione contabile 2.0, AccountingType con la 1.0. Senza conto il trasferimento resta in attesa.',
    ],
];
