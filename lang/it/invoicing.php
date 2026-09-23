<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'service' => 'Prestazione',
    'service_on' => 'Prestazione del :date',
    'hourly_rate' => 'Tariffa oraria',
    'unit_hour' => 'h',
    'unit_flat' => 'forf.',
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
        'gaeb' => [
            'button' => 'GAEB (X89)',
            'button_title' => 'Scarica la fattura come file GAEB per i committenti edili',
        ],
        'zugferd' => [
            'button' => 'ZUGFeRD (PDF)',
            'button_title' => 'Scarica il PDF ZUGFeRD (PDF/A-3, EN 16931)',
            'error_intro' => 'Impossibile generare il PDF ZUGFeRD:',
            'unavailable' => 'La generazione del PDF ZUGFeRD non è disponibile su questo sistema (php-pdf-toolkit mancante).',
            'failed' => 'La generazione del PDF ZUGFeRD non è riuscita.',
        ],
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

    // Anteprima della fattura nel dialogo di creazione (MVP-462).
    'source_times' => 'Mostra :count registrazione di tempo di origine|Mostra :count registrazioni di tempo di origine',
    'preview' => [
        'heading' => 'Anteprima:',
        'empty' => 'Nessun tempo fatturabile o trasferta per i filtri selezionati.',
        'entry_count' => ':count registrazione|:count registrazioni',
        'travel' => '+ :count trasferta/e',
        'warning_late' => ':count registrazione tardiva: la data di prestazione ricade in un periodo già fatturato.|:count registrazioni tardive: le date di prestazione ricadono in periodi già fatturati.',
        'column' => [
            'description' => 'Posizione',
            'duration' => 'Durata',
            'rate' => 'Tariffa',
            'amount' => 'Importo',
        ],
        'entries_heading' => 'Mostra/escludi singole registrazioni',
        'exclude' => 'escludi',
        'exclude_hint' => 'Le registrazioni escluse restano aperte e riappaiono nel prossimo ciclo di fatturazione.',
    ],
    // Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
    'girocode' => [
        'alt' => 'Codice QR di pagamento',
        'hint' => 'Scansiona con l’app bancaria',
    ],
    // Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
    'retention' => [
        'dialog_title' => 'Registra una ritenuta',
        'submit' => 'Registra',
        'dialog_hint' => 'La ritenuta compare sul documento e viene dedotta dalla partita aperta. Dopo l’emissione non è più modificabile.',
        'kind' => 'Tipo',
        'basis' => 'Base',
        'basis_percent' => 'Percentuale del totale fattura',
        'basis_amount' => 'Importo fisso',
        'base_kind' => 'Base di calcolo',
        'percent' => 'Percentuale',
        'amount' => 'Importo fisso',
        'due_on' => 'Pagabile dal',
        'due_on_hint' => 'Da questo giorno la ritenuta è una normale partita aperta e viene di nuovo sollecitata.',
        'note' => 'Nota',
        'heading' => 'Ritenute a garanzia',
        'action' => 'Registra ritenuta',
        'release' => 'Libera',
        'column_kind' => 'Tipo',
        'column_amount' => 'Importo',
        'column_due' => 'Pagabile dal',
        'column_status' => 'Stato',
        'payable' => 'Importo da pagare',
        'locked' => 'Le ritenute a garanzia si possono modificare solo sulla bozza di fattura — compaiono sul documento e dopo l’emissione fanno parte dello stato congelato.',
        'needs_one_basis' => 'Indicare una percentuale OPPURE un importo fisso.',
        'no_total' => 'Il documento non ha ancora un totale a cui riferire una ritenuta.',
        'amount_positive' => 'La ritenuta deve essere maggiore di zero.',
        'exceeds_total' => 'Le ritenute superano il totale della fattura.',
        'not_open' => 'Questa ritenuta non è più aperta.',
        'pdf_line' => 'meno :basis :kind ai sensi del § 17 VOB/B',
        'pdf_due' => 'pagabile dal :date',
        'pdf_payable' => 'Importo da pagare',
        'dunning_note' => 'meno la ritenuta a garanzia',
        'added' => 'Ritenuta registrata.',
        'released' => 'Ritenuta liberata.',
    ],

    // Leistungszeitraum je Position (Feature 152, Review 2026-09-11).
    // Freie Rechnungen aus Artikeln, Material und Fertigung (Feature 160, MVP-856–859).
    'free' => [
        'title' => [
            'create' => 'Crea fattura',
            'deliveries' => 'Riprendere consegne',
        ],
        'option' => [
            'manual' => 'Comporre le righe manualmente (articoli, materiale, produzione)',
            'no_variant' => '— senza variante —',
        ],
        'field' => [
            'variant' => 'Variante',
            'delivery' => 'Consegna',
            'order' => 'Ordine di produzione',
            'delivered_on' => 'Consegnato il',
        ],
        'action' => [
            'attach_deliveries' => 'Riprendi consegna',
            'open_order' => 'Apri ordine di produzione',
        ],
        'hint' => [
            'manual' => 'Nessun periodo, nessun tempo: la bozza parte vuota; le righe provengono da articoli, materiale, testo libero o consegne di produzione.',
            'empty_draft' => 'Aggiunga righe o riprenda una consegna. Una bozza vuota non può essere emessa né inviata.',
            'variant' => 'Facoltativa; la variante precompila prezzo e numero articolo.',
            'no_stock_movement' => 'Le righe articolo e a testo libero non movimentano il magazzino; una consegna si registra tramite magazzino/consegna.',
            'price_required' => 'Inserire consapevolmente — 0,00 per una riga gratuita è ammesso.',
            'currency_mismatch' => 'Il prezzo dell\'articolo è in una valuta diversa dal documento; inserisca il prezzo nella valuta del documento.',
            'deliveries' => 'Consegne effettuate e non ancora fatturate del cliente nel periodo dell\'intestazione :from – :to; ogni consegna viene ripresa per intero come una riga.',
            'deliveries_empty' => 'I prodotti senza consegna si vendono come riga articolo libera.',
            'deliveries_rules' => 'La quantità è vincolata alla fonte, il prezzo di vendita proviene dalla consegna e resta modificabile nella bozza. Rimuovere la riga o scartare la bozza libera la consegna; il magazzino resta invariato.',
        ],
        'label' => [
            'from_order' => 'ordine di produzione :number',
            'source_delivery' => 'Consegna del :date · :order · quantità consegnata :quantity',
            'reserved' => 'riservata nella bozza',
        ],
        'empty' => [
            'deliveries' => 'Nessuna consegna aperta.',
        ],
        'flash' => [
            'draft_created' => 'Bozza di fattura creata — aggiunga ora le righe.',
            'deliveries_attached' => ':count consegna/e ripresa/e.',
        ],
        'error' => [
            'empty' => 'La bozza non ha righe e non può essere emessa né inviata.',
            'variant_mismatch' => 'La variante non appartiene all\'articolo scelto.',
            'draft_only' => 'Le consegne si riprendono solo in una bozza di fattura normale.',
            'delivery_required' => 'Selezioni almeno una consegna.',
            'delivery_foreign' => 'La consegna non appartiene a questa organizzazione.',
            'delivery_customer' => 'La consegna appartiene a un altro cliente.',
            'delivery_external' => 'La consegna viene fatturata esternamente.',
            'delivery_not_delivered' => 'La consegna non è ancora avvenuta.',
            'delivery_invoiced' => 'La consegna è già fatturata.',
            'delivery_reserved' => 'La consegna «:name» è già riservata nella bozza :number.',
            'delivery_currency' => 'La consegna è in :currency, il documento in :invoice — nessuna conversione automatica.',
            'delivery_project' => 'La consegna appartiene a un altro progetto.',
            'delivery_without_price' => 'La consegna «:name» non ha prezzo di vendita — lo registri sull\'articolo/variante o inserisca una riga libera.',
        ],
    ],

    'item' => [
        'service_period' => 'Periodo di prestazione',
        'service_from' => 'Periodo di prestazione dal',
        'service_to' => 'Periodo di prestazione al',
    ],
];
