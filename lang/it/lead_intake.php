<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Fascicolo di ammissione con pacchetti di verifica (Feature 200, MVP-1641–1644, MVP-1675).
return [
    'title' => 'Fascicolo di ammissione',
    'title_for' => 'Fascicolo di ammissione: :name',
    'index_title' => 'Ammissioni',
    'index_subtitle' => 'Fascicoli di ammissione dei lead, dalla richiesta all\'attivazione del cliente.',
    'back_to_lead' => 'Al lead',
    'revision' => 'Versione :revision',
    'superseded' => 'Sostituita',
    'stages' => 'Fasi',
    'current_stage' => 'Fase attuale',

    'action' => [
        'open' => 'Apri ammissione',
        'decide' => 'Decidi',
        'refresh' => 'Aggiorna',
        'pdf' => 'PDF',
        'archive' => 'Archivia come documento',
        'move_to' => 'Avanti: :stage',
        'save' => 'Salva',
        'confirm_second_review' => 'Conferma accettazione',
        'return_second_review' => 'Restituisci',
    ],
    'confirm' => [
        'refresh' => 'Creare una nuova versione del fascicolo? I dati vengono ripresi e possono essere verificati e completati.',
        'archive' => 'Archiviare il PDF senza dati di identità e di salute come documento?',
        'second_review' => 'Confermare l\'accettazione come seconda persona?',
    ],
    'hint' => [
        'restricted' => ':count dati di questo fascicolo richiedono un permesso proprio e sono nascosti.',
        'restricted_answered' => ':kind: :count compilati.',
        'no_packages' => 'Nessun pacchetto di verifica attivo. I fascicoli contengono solo il modulo del colloquio; i lead si convertono direttamente come finora.',
    ],

    'form' => [
        'section_base' => 'Richiesta e colloquio',
        'request' => 'Richiesta',
        'services' => 'Servizi desiderati',
        'services_help' => 'Alla conversione diventa l\'ambito dei servizi del nuovo cliente.',
        'start_on' => 'Inizio desiderato',
        'interview_notes' => 'Note del colloquio',
        'section_traits' => 'Caratteristiche delle scadenze: :rule_set',
        'traits_help' => 'Alla conversione diventano caratteristiche delle scadenze del nuovo cliente.',
        'section_documents' => 'Documenti: :package',
        'documents_help' => 'Spuntare appena il documento è disponibile; archiviare il file qui sotto, nei documenti.',
    ],

    'field' => [
        'stage' => 'Fase',
        'decision' => 'Decisione',
        'decision_reason' => 'Motivazione',
        'decided_at' => 'Deciso il',
        'decided_by' => 'Deciso da',
        'second_review' => 'Secondo controllo',
        'protocol' => 'Nota',
        'packages' => 'Pacchetti di verifica',
        'refresh_due_on' => 'Aggiornamento dovuto',
        'retention_until' => 'Conservare fino al',
        'customer' => 'Cliente',
        'relationship_ended_on' => 'Fine del rapporto d\'affari',
    ],

    'documents' => [
        'title' => 'Documenti',
        'upload' => 'Archivia',
        'upload_for' => 'File per «:document»',
    ],

    'decision' => [
        'title' => 'Decisione',
        'legend' => 'Accettare o rifiutare l\'ammissione',
        'open' => 'Non ancora deciso.',
        'missing' => 'Manca ancora prima dell\'accettazione: :count',
        'blocks_conversion' => 'È attivo un pacchetto di verifica con obbligo di decisione: il lead diventa cliente solo dopo l\'accettazione.',
        'reason_hint' => 'La motivazione resta nel fascicolo e nel PDF.',
        'second_review_pending' => 'Accettato — manca il secondo controllo di un\'altra persona.',
        'return_reason' => 'Motivazione della restituzione',
    ],

    'packages' => [
        'title' => 'Pacchetti di verifica e scadenze',
        'none' => 'Nessuno',
    ],
    'versions' => [
        'title' => 'Versioni',
    ],
    'retention' => [
        'open' => 'decorre dalla fine del rapporto d\'affari',
    ],

    'settings' => [
        'title' => 'Impostazioni delle ammissioni',
        'submit' => 'Salva',
        'packages' => 'Pacchetti di verifica',
        'none_available' => 'Nessun pacchetto di verifica disponibile.',
        'packages_hint' => 'I pacchetti attivi valgono per i fascicoli aperti da ora. Un pacchetto con obbligo di decisione blocca la conversione senza accettazione.',
        'refresh' => 'Aggiornamento per livello di rischio GwG (mesi)',
        'refresh_for' => 'Livello di rischio :level',
        'refresh_hint' => 'Predefinito: annuale. Il termine decorre dall\'accettazione.',
        'second_review' => 'Secondo controllo',
        'second_review_label' => 'Far confermare l\'accettazione da una seconda persona',
        'second_review_hint' => 'Predefinito: disattivato. La seconda persona deve avere il permesso di decisione e non aver accettato lei stessa.',
    ],

    'relationship' => [
        'title' => 'Fine del rapporto d\'affari',
        'submit' => 'Salva',
        'legend' => 'Rapporto d\'affari',
        'hint' => 'Dalla fine di questo anno solare decorre la conservazione dei fascicoli di ammissione (GwG: cinque anni, § 8 c. 4 GwG). Lasciare vuoto finché dura.',
        'ongoing' => 'in corso',
    ],

    'cockpit' => [
        'none' => 'Ancora nessun fascicolo di ammissione. Pacchetti attivi: :packages',
        'refresh_due' => 'Aggiornamento :date',
        'retention' => 'conservare fino al :date',
    ],

    'filter' => [
        'status' => 'Selezione',
        'status_open' => 'Ammissioni aperte',
        'status_refresh' => 'Aggiornamento dovuto',
        'status_all' => 'Tutte',
        'stage' => 'Fase',
        'all_stages' => 'Tutte le fasi',
    ],
    'empty' => [
        'title' => 'Nessuna ammissione',
        'message' => 'I fascicoli di ammissione si aprono sul lead.',
    ],
    'column' => [
        'lead' => 'Lead',
        'stage' => 'Fase',
        'packages' => 'Pacchetti di verifica',
        'decision' => 'Decisione',
        'refresh_due_on' => 'Aggiornamento',
        'updated_at' => 'Modificato',
    ],

    'widget' => [
        'title' => 'Ammissioni',
        'description' => 'Fascicoli di ammissione aperti per fase, dalla richiesta all\'attivazione.',
        'open_list' => 'Tutte le ammissioni',
        'empty' => 'Al momento non c\'è nessuna ammissione aperta.',
    ],

    'flash' => [
        'opened' => 'Fascicolo di ammissione aperto.',
        'saved' => 'Modulo salvato.',
        'stage' => 'Fase: :stage',
        'decided' => 'Deciso: :decision',
        'second_review_confirmed' => 'Accettazione confermata.',
        'second_review_returned' => 'Accettazione restituita — il modulo è di nuovo aperto.',
        'document_added' => 'Documento archiviato.',
        'refreshed' => 'Versione :revision creata.',
        'archived' => 'Archiviato come documento: :file',
        'settings_saved' => 'Impostazioni delle ammissioni salvate.',
        'relationship_saved' => 'Salvato; conservazione di :count fascicoli ricalcolata.',
    ],

    'error' => [
        'anonymized' => 'Il lead è anonimizzato.',
        'stage_by_decision' => 'Attivazione, «attivato» e «rifiutato» derivano dalla decisione o dalla conversione.',
        'closed' => 'Il fascicolo è deciso o sostituito da una versione più recente.',
        'reason_required' => 'Motivare la decisione.',
        'no_second_review' => 'Questa accettazione non attende un secondo controllo.',
        'same_person' => 'Il secondo controllo è svolto da una persona diversa da chi ha accettato.',
        'refresh_needs_acceptance' => 'Si aggiorna solo la versione accettata più recente.',
        'unknown_document' => 'Questo documento non appartiene al fascicolo.',
        'restricted' => 'Manca il permesso proprio per questo documento.',
        'no_carrier' => 'Nessun cliente né lead trovato per l\'archiviazione.',
        'restricted_missing' => 'Manca un dato con permesso proprio (:right).',
    ],

    'block' => [
        'no_intake' => 'È attivo un pacchetto di verifica con obbligo di decisione: aprire prima il fascicolo di ammissione e accettare l\'ammissione.',
        'not_accepted' => 'L\'ammissione non è ancora accettata — solo dopo il lead diventa cliente.',
        'second_review' => 'L\'accettazione attende il secondo controllo.',
        'rejected' => 'L\'ammissione è stata rifiutata; il lead non viene convertito.',
    ],

    'transfer' => [
        'service_note' => 'Ripreso dal fascicolo di ammissione.',
        'traits_incomplete' => 'Le caratteristiche delle scadenze «:rule_set» del fascicolo sono incomplete e non sono state riprese — completarle sul cliente.',
    ],

    'notification' => [
        'refresh' => [
            'title' => ':count fascicolo/i di ammissione da aggiornare',
            'message' => 'Dovuto dal :date: :names',
        ],
    ],

    'work' => [
        'decide' => 'Decidere l\'ammissione: :name',
        'second_review' => 'Secondo controllo: :name',
        'refresh' => 'Aggiornare l\'ammissione: :name',
    ],

    'pdf' => [
        'file_prefix' => 'ammissione',
        'customer' => 'Cliente',
        'restricted' => 'riservato (:kind)',
    ],

    'vat_id' => [
        'invalid' => 'La partita IVA è formalmente non valida (struttura o cifra di controllo).',
    ],

    'dsar' => [
        'title' => 'Fascicoli di ammissione',
        'list_title' => 'Fascicolo di ammissione, versione :revision',
        'column' => [
            'topic' => 'Dato',
            'value' => 'Contenuto',
        ],
        'legal_basis' => 'Base giuridica della conservazione',
        'legal_basis_value' => 'Art. 6, par. 1, lett. c GDPR in combinato disposto con :basis',
        'restricted' => 'presente — comunicazione solo con il permesso «:right»',
    ],

    'demo' => [
        'request' => 'Contabilità corrente, paghe di tre dipendenti e bilancio annuale dal mese prossimo.',
        'notes' => 'Primo colloquio in studio; i documenti del consulente precedente arriveranno tramite caricamento.',
        'purpose' => 'Consulenza fiscale: contabilità, paghe e bilancio annuale della società.',
    ],

    'gwg' => [
        'label' => 'Legge tedesca antiriciclaggio (GwG)',
        'protocol_title' => 'Identificazione e nota di rischio: :name',
        'protocol_description' => 'Dal fascicolo di ammissione (versione :revision). Livello di rischio: :risk. Motivazione: :reason',
        'document' => [
            'id_copy' => 'Copia del documento d\'identità o della visura camerale (§ 8 c. 2 GwG)',
        ],
        'section' => [
            'identification' => 'GwG: Identificazione della controparte (§ 11 GwG)',
            'verification' => 'GwG: Verifica dell\'identità (§ 12 GwG)',
            'beneficial_owners' => 'GwG: Titolari effettivi (§ 3 GwG)',
            'pep' => 'GwG: Persona politicamente esposta (§ 15 c. 3 n. 1 GwG)',
            'purpose' => 'GwG: Scopo e natura del rapporto d\'affari (§ 10 c. 1 n. 3 GwG)',
            'risk' => 'GwG: Valutazione del rischio (§§ 14, 15 GwG)',
        ],
        'field' => [
            'party_kind' => 'La controparte è',
            'first_name' => 'Nome',
            'last_name' => 'Cognome',
            'birth_place' => 'Luogo di nascita',
            'birth_date' => 'Data di nascita',
            'nationality' => 'Cittadinanza',
            'home_address' => 'Indirizzo di residenza',
            'company_name' => 'Ragione sociale, nome o denominazione',
            'legal_form' => 'Forma giuridica',
            'register_number' => 'Numero di registro',
            'seat_address' => 'Indirizzo della sede legale o principale',
            'representatives' => 'Membri dell\'organo di rappresentanza o rappresentanti legali',
            'verification_method' => 'Verificato tramite',
            'id_document_kind' => 'Tipo di documento',
            'id_document_number' => 'Numero del documento',
            'id_issuer' => 'Autorità di rilascio',
            'verified_on' => 'Verificato il',
            'beneficial_owners' => 'Titolari effettivi',
            'transparency_register_checked' => 'Registro della trasparenza consultato',
            'transparency_register_on' => 'Registro della trasparenza: consultato il',
            'transparency_register_reference' => 'Registro della trasparenza: estratto o riferimento',
            'pep_status' => 'Persona politicamente esposta',
            'pep_details' => 'Dettagli sulla carica o sul rapporto',
            'purpose' => 'Scopo e natura prevista del rapporto d\'affari',
            'suspicion_checked' => 'Indizi di riciclaggio o finanziamento del terrorismo verificati',
            'lower_risk_factors' => 'Fattori di rischio inferiore (allegato 1 GwG)',
            'higher_risk_factors' => 'Fattori di rischio più elevato (allegato 2 GwG)',
            'risk_level' => 'Livello di rischio',
            'risk_reason' => 'Motivazione del livello di rischio',
        ],
        'help' => [
            'party_kind' => 'Persona fisica: § 11 c. 4 n. 1 GwG; persona giuridica o società di persone: n. 2.',
            'verification_method' => 'Con documento d\'identità o visura: annotare tipo, numero e autorità di rilascio e archiviare una copia (§ 8 c. 2 GwG).',
            'id_document_number' => 'Salvato cifrato e visibile solo con il permesso per i dati di identità.',
            'beneficial_owners' => 'Nome, tipo ed entità della partecipazione o del controllo; per una persona fisica che agisce per sé, la persona stessa.',
            'transparency_register_checked' => 'Prova dell\'iscrizione o estratto dal registro della trasparenza (§ 11 c. 5, § 12 c. 3 GwG).',
            'pep_status' => 'A una persona politicamente esposta si applicano obblighi rafforzati — il livello di rischio è quindi alto.',
            'suspicion_checked' => 'Il fascicolo registra solo la verifica; l\'app non invia segnalazioni alla FIU.',
            'lower_risk_factors' => 'Fattori non esaustivi secondo l\'allegato 1 GwG.',
            'higher_risk_factors' => 'Fattori non esaustivi secondo l\'allegato 2 GwG.',
            'risk_level' => 'Basso: adeguata verifica semplificata (§ 14), normale: ordinaria (§ 10), alto: rafforzata (§ 15). Determina il termine di aggiornamento.',
            'risk_reason' => 'Obbligatoria per basso e alto (§ 8 c. 1 frase 1 n. 2 GwG).',
        ],
        'option' => [
            'party_kind' => [
                'natural' => 'Persona fisica',
                'legal' => 'Persona giuridica o società di persone',
            ],
            'verification_method' => [
                'id_document' => 'Documento ufficiale con foto (carta d\'identità, passaporto)',
                'eid' => 'Identità elettronica (eID)',
                'qes' => 'Firma elettronica qualificata',
                'register_extract' => 'Visura del registro delle imprese o delle cooperative',
                'formation_documents' => 'Atti costitutivi',
                'own_register_check' => 'Consultazione propria e documentata del registro',
            ],
            'pep_status' => [
                'no' => 'No',
                'pep' => 'Sì, politicamente esposta',
                'family' => 'Familiare di una persona politicamente esposta',
                'associate' => 'Persona notoriamente legata',
            ],
            'lower_risk_factors' => [
                'listed_company' => 'Società quotata soggetta a obblighi di informativa',
                'public_body' => 'Pubblica amministrazione o impresa pubblica',
                'low_risk_area' => 'Sede o residenza in un\'area a rischio inferiore',
                'low_risk_product' => 'Servizio a basso rischio',
            ],
            'higher_risk_factors' => [
                'unusual_circumstances' => 'Circostanze insolite del rapporto d\'affari',
                'high_risk_area' => 'Sede o residenza in un\'area ad alto rischio',
                'asset_holding_vehicle' => 'Società di gestione di patrimoni privati',
                'nominee_shareholders' => 'Azionisti fiduciari o azioni al portatore',
                'cash_intensive' => 'Attività ad alta intensità di contante',
                'complex_structure' => 'Struttura proprietaria insolitamente complessa',
                'no_personal_contact' => 'Nessun contatto personale senza misure di sicurezza',
                'unknown_third_party_payments' => 'Pagamenti da terzi sconosciuti o non collegati',
                'high_risk_goods' => 'Commercio di petrolio, armi, metalli preziosi, tabacco, beni culturali o specie protette',
            ],
        ],
        'error' => [
            'pep_requires_high' => 'A una persona politicamente esposta si applicano obblighi rafforzati: scegliere il livello di rischio «alto» (§ 15 c. 3 n. 1 GwG).',
            'copy_missing' => 'Manca la copia del documento (§ 8 c. 2 GwG).',
        ],
    ],
];
