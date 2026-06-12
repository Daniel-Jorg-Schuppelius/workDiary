<?php

return [
    'service' => 'Prestazione',
    'service_on' => 'Prestazione del :date',
    'hourly_rate' => 'Tariffa oraria',
    'unit_hour' => 'h',
    'unit_piece' => 'pz',
    'tax_rate' => 'Aliquota fiscale',
    'currency' => 'Valuta',
    'totals' => [
        'net' => 'Netto',
        'tax' => 'Imposta',
        'gross' => 'Lordo',
    ],

    // Fatturazione elettronica (funzionalità 045, sezione 8): XRechnung (UBL 2.1, EN 16931).
    'buyer_reference' => 'Leitweg-ID / riferimento acquirente (BT-10)',
    'buyer_reference_hint' => 'Obbligatorio per la XRechnung (fattura elettronica): il Leitweg-ID per le amministrazioni, altrimenti un riferimento fornito dal cliente.',
    'einvoice' => [
        'button' => 'XRechnung',
        'button_title' => 'Scarica la XRechnung (UBL 2.1, EN 16931)',
        'error_intro' => 'Impossibile generare la XRechnung:',
        'payment_terms' => 'Pagabile entro :days giorni senza sconto.',
        'exemption_small_business' => 'Nessuna IVA applicata ai sensi del § 19 UStG (regime tedesco delle piccole imprese).',
        'error' => [
            'status' => 'La fattura deve essere emessa o pagata.',
            'no_items' => 'La fattura non contiene posizioni.',
            'missing_buyer_reference' => 'Al cliente manca il Leitweg-ID/riferimento acquirente (BT-10).',
            'missing_seller_field' => 'Dato del venditore mancante: :field (impostazioni dell\'organizzazione → fatturazione).',
            'missing_tax_id' => 'Né partita IVA né codice fiscale configurati nelle impostazioni dell\'organizzazione.',
            'missing_iban' => 'Manca l\'IBAN per il bonifico SEPA nelle impostazioni dell\'organizzazione.',
            'missing_tax_rate' => 'La fattura non riporta alcuna aliquota fiscale.',
            'totals_mismatch' => 'I totali della fattura sono incoerenti (posizioni, subtotale, imposta, totale).',
        ],
        'warning' => [
            'missing_seller_contact' => 'Contatto del venditore incompleto (nome, telefono, e-mail) — la XRechnung richiede dati di contatto completi (BR-DE-2).',
            'missing_bic' => 'Manca il BIC (consigliato per i bonifici SEPA).',
            'buyer_address_incomplete' => 'Indirizzo del cliente incompleto (via/CAP/città).',
            'missing_buyer_email' => 'Manca l\'e-mail del cliente (indirizzo elettronico di ricezione BT-49).',
            'missing_due_date' => 'Data di scadenza mancante — viene usato il termine di pagamento predefinito.',
        ],
    ],
];
