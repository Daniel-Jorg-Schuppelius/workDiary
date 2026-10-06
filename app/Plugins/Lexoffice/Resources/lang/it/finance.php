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
        'lexoffice_contact_missing' => 'Nessun contatto Lexoffice per il cliente — sincronizzare prima il contatto.',
        'lexoffice_delivery_no_customer' => 'Una consegna senza cliente non può essere trasmessa come documento di trasporto.',
        'lexoffice_delivery_not_linked' => 'Nessun documento di trasporto Lexoffice è collegato a questa consegna.',
        'lexoffice_dunning_not_invoice' => 'Un sollecito può essere creato solo per una fattura.',
        'lexoffice_not_configured' => 'Lexoffice non è configurato per questa organizzazione (chiave API mancante).',
        'lexoffice_outcome_unclear' => 'Esito del trasferimento a Lexoffice incerto (timeout dopo l\'invio): non ripetere alla cieca; la prossima esecuzione cerca la bozza tramite il marcatore di origine.',
        'lexoffice_oc_no_customer' => 'Un ordine di produzione senza cliente non può essere trasmesso come conferma d\'ordine.',
        'lexoffice_oc_not_linked' => 'Nessuna conferma d\'ordine Lexoffice è collegata a questo ordine di produzione.',
        'lexoffice_quote_no_customer' => 'Un ordine di produzione senza cliente non può essere trasmesso come offerta.',
        'lexoffice_quote_not_linked' => 'Nessuna offerta Lexoffice è collegata a questo ordine di produzione.',
    ],
    'lexoffice' => [
        'introduction' => 'Vi fatturiamo come segue le nostre forniture e prestazioni.',
        'delivery_title' => 'Documento di trasporto',
        'transfer_marker' => 'Riferimento di trasferimento :marker',
    ],
];
