<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lizenz-Reselling-Abgleich (Feature 151, MVP-757).
return [
    'title' => [
        'menu' => 'Riconciliazione licenze',
        'index' => 'Riconciliazione rivendita licenze',
        'show' => 'Esecuzione della riconciliazione',
    ],
    'subtitle' => 'Confrontare gli abbonamenti marketplace (Telekom, Quality Hosting) con le fatture emesse in Lexoffice: periodi mancanti, parziali o fatturati sotto costo, più un controllo prezzi sul listino rivenditore.',
    'action' => [
        'new' => 'Nuova esecuzione',
        'download' => 'CSV',
        'delete' => 'Elimina',
        'refresh' => 'Aggiorna',
        'assign' => 'Assegna',
        'rerun' => 'Ricalcola',
        'remove_mapping' => 'Rimuovi assegnazione',
        'back' => 'Torna alla panoramica',
    ],
    'dialog' => [
        'title' => 'Avviare una nuova esecuzione',
        'hint' => 'È necessario almeno un file di export. L’esecuzione legge Lexoffice in background; con molti clienti richiede alcuni minuti.',
        'telekom' => 'Telekom Cloud Marketplace: purchases.csv',
        'qualityhosting' => 'Quality Hosting: export contratti (.xlsx)',
        'pricelist' => 'Quality Hosting: listino prezzi (.xlsx, facoltativo)',
        'map' => 'File di assegnazione (facoltativo)',
        'map_hint' => 'Una riga per azienda: «Azienda;UUID contatto Lexoffice» oppure «Azienda;customer:<Sqid>». Per tutto ciò che l’esecuzione non assegna in modo univoco.',
        'reference' => 'Data di riferimento',
        'reference_hint' => 'I periodi iniziati entro questo giorno contano come dovuti. Non c’è limite verso il passato: viene verificato tutto dal primo inizio contratto negli export.',
        'before' => 'Giorni prima dell’inizio periodo (pagamento anticipato)',
        'after' => 'Giorni dopo l’inizio periodo (fatturazione tardiva)',
        'window_hint' => 'Una fattura appartiene a un periodo se la sua data cade in questa finestra intorno all’inizio del periodo. Lasciare ampia la parte posteriore (due anni per impostazione predefinita): fatturazioni tardive e blocchi pluriennali vengono ripartiti in mesi-licenza, nulla conta due volte.',
        'strict' => 'Controllo rigoroso del prodotto',
        'strict_hint' => 'Contare solo le righe di fattura il cui testo nomina l’edizione. Senza spunta, conta ogni riga Microsoft del contatto nella finestra se non si trova un’edizione corrispondente (fatture cumulative).',
        'submit' => 'Avvia',
    ],
    'field' => [
        'created' => 'Avviata', 'status' => 'Stato', 'sources' => 'Fonti', 'reference' => 'Data di riferimento',
        'periods' => 'Periodi', 'problems' => 'Segnalati', 'open_fee' => 'Costo d’acquisto aperto', 'unmapped' => 'Senza assegnazione',
        'window' => 'Finestra', 'files' => 'File', 'by' => 'Da', 'error' => 'Errore', 'price_flags' => 'Avvisi prezzo',
        'company' => 'Azienda', 'customer' => 'Cliente', 'contact' => 'Contatto Lexoffice', 'mapping' => 'Assegnazione', 'candidates' => 'Candidati',
        'source' => 'Fonte', 'edition' => 'Edizione', 'period' => 'Periodo', 'quantity' => 'Quantità', 'purchase' => 'Acquisto',
        'vouchers' => 'Fattura/e', 'unit_net' => 'Netto per unità', 'note' => 'Nota', 'succession' => 'Successione',
        'voucher' => 'Fattura', 'date' => 'Data', 'position' => 'Riga', 'remaining' => 'Residuo',
        'product' => 'Prodotto', 'term' => 'Durata', 'running' => 'Unità attive', 'contract_price' => 'Acquisto (contratto)', 'list_price' => 'Acquisto (listino)',
        'uvp' => 'Prezzo consigliato', 'sales' => 'Vendita (mediana, numero)', 'sales_range' => 'Vendita min – max', 'margin' => 'Margine vs listino',
        'telekom_from' => 'Telekom da', 'telekom_to' => 'Telekom fino a', 'successor' => 'Contratto QH', 'successor_from' => 'QH da',
        'billed_via' => 'Fatturato tramite partner (cliente terzo)',
        'stored_mapping' => 'Assegnazione salvata',
        'used' => 'Utilizzato', 'recognized' => 'Riconosciuto come',
        'article_price' => 'Prezzo articolo (anno)',
        'valid_from' => 'Listino valido dal',
    ],
    'status' => [
        'queued' => 'In coda',
        'running' => 'In esecuzione',
        'done' => 'Completata',
        'failed' => 'Fallita',
    ],
    'section' => [
        'lines' => 'Righe di fattura trovate per i contatti assegnati',
        'summary' => 'Riepilogo', 'price' => 'Controllo prezzi', 'findings' => 'Periodi', 'mappings' => 'Assegnazione azienda marketplace → contatto Lexoffice',
        'extras' => 'Righe Microsoft senza periodo dovuto', 'successions' => 'Successioni Telekom → Quality Hosting', 'issues' => 'Note dai file', 'errors' => 'Errori di lettura', 'files' => 'File e opzioni',
    ],
    'filter' => [
        'status' => 'Stato', 'problems' => 'Solo segnalati', 'all' => 'Tutti', 'company' => 'Azienda', 'all_companies' => 'Tutte le aziende',
    ],
    'empty' => [
        'lines' => 'Nessuna riga di fattura trovata.',
        'runs' => 'Nessuna esecuzione. Carica i file di export per avviare la prima riconciliazione.', 'findings' => 'Nessun periodo in questa selezione.', 'price' => 'Nessun contratto attivo o nessun listino caricato.', 'mappings' => 'Nessuna azienda.', 'extras' => 'Nessuna riga aggiuntiva.', 'successions' => 'Nessuna successione rilevata.',
    ],
    'price_flag' => [
        'below_list' => 'sotto il prezzo d’acquisto', 'below_uvp' => 'sotto il prezzo consigliato', 'contract_above_list' => 'contratto più caro del listino', 'no_sales' => 'nessun dato di fattura', 'no_list' => 'non nel listino',
    ],
    'flash' => [
        'mapping_saved' => 'Assegnazione salvata. Con «Ricalcola» ha effetto nel report.', 'mapping_removed' => 'Assegnazione rimossa.', 'rerun' => 'L’esecuzione viene ricalcolata.',
        'created' => 'Esecuzione avviata. Il report apparirà qui appena Lexoffice è stato letto.', 'deleted' => 'Esecuzione eliminata.', 'not_done' => 'L’esecuzione non è ancora terminata.',
    ],
    'validation' => [
        'customer_required' => 'Seleziona un cliente.', 'contact_required' => 'Indica un UUID di contatto Lexoffice.',
        'need_file' => 'È necessario almeno un file di export (Telekom o Quality Hosting).',
    ],
    'hint' => [
        'lines' => 'Diagnosi: tutto ciò che la riconciliazione ha visto in Lexoffice per i contatti assegnati nel periodo, con la quantità utilizzata. Un’azienda senza righe qui non ha fatture per il suo contatto nel periodo.',
        'lines_hidden' => ':count posizioni senza attinenza alle licenze (servizi propri, hardware, domini) sono nascoste.',
        'run_pending' => 'L’esecuzione non è ancora terminata. Aggiorna la pagina per vedere il report.', 'run_failed' => 'L’esecuzione è fallita.', 'unmapped' => 'Le aziende senza assegnazione si risolvono con un file di assegnazione alla prossima esecuzione.', 'extras' => 'Fatturato senza abbonamento attivo, oppure edizione non riconosciuta dalla riconciliazione.',
        'mapping' => 'Con «Assegna» definisci per azienda chi riceve la fattura: l’azienda stessa, un partner o un contatto Lexoffice. Le assegnazioni salvate hanno la precedenza sul riconoscimento automatico.',
        'foreign' => 'I clienti finali di un partner (clienti terzi) vengono verificati tramite il partner: la fattura va al partner, che la gira. Crea i clienti terzi sotto il cliente partner, oppure aggiungi «Azienda;partner:<nome o Sqid>» al file di assegnazione.',
        'succession' => 'La durata Telekom è stata troncata all’inizio del contratto Quality Hosting; altrimenti ogni migrazione conterebbe due volte.', 'price' => 'I prezzi di vendita provengono dalle righe di fattura assegnate; prezzo d’acquisto di listino e prezzo consigliato dal listino per la stessa durata e lo stesso ritmo. Il prezzo articolo è il tuo prezzo di vendita attuale dall’anagrafica articoli Lexoffice, riportato alla durata; senza dati di fattura funge da riferimento.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'mapping' => [
        'title' => 'Assegna azienda',
        'submit' => 'Salva assegnazione',
        'hint' => 'L’assegnazione vale per tutte le esecuzioni future di questa organizzazione. Poi «Ricalcola» perché abbia effetto nel report.',
        'mode_label' => 'Fatturazione',
        'mode' => [
            'customer' => 'Direttamente: l’azienda è il cliente',
            'partner' => 'Tramite un partner (cliente terzo)',
            'contact' => 'Contatto Lexoffice',
        ],
        'mode_hint' => [
            'customer' => 'La fattura va a questo cliente stesso.',
            'partner' => 'Il cliente scelto riceve la fattura e la gira. L’azienda viene creata come cliente terzo presso di lui se manca.',
            'contact' => 'Senza anagrafica: vengono verificate le fatture di questo contatto Lexoffice.',
        ],
        'customer' => 'Cliente o partner',
        'customer_placeholder' => 'Scegli cliente',
        'contact' => 'UUID contatto Lexoffice',
        'contact_hint' => 'Necessario solo per «Contatto Lexoffice»; si trova nell’URL Lexoffice del contatto.',
    ],
    'line' => [
        'header_only' => 'Documento senza righe',
        'microsoft' => 'Riga Microsoft',
        'other' => 'Altro',
    ],
    'months' => 'mesi',
];
