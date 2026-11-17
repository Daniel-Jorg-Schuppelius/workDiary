<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Bassa',
        'normal' => 'Normale',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'In sede',
        'remote' => 'Da remoto',
        'hybrid' => 'Ibrido',
    ],
    'mode' => [
        'fixed' => 'Pianificato',
        'deadline' => 'Scadenza',
        'window' => 'Finestra',
        'recurring' => 'Ricorrente',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Pianificato',
        'Accepted' => 'Accettato',
        'InProgress' => 'In lavorazione',
        'WaitingCustomer' => 'In attesa di riscontro',
        'WaitingMaterial' => 'In attesa di materiale',
        'Completed' => 'Completato',
        'AcceptedFinal' => 'Collaudato',
        'Invoiced' => 'Fatturato',
        'Cancelled' => 'Annullato',
        'InReview' => 'In verifica',
        'ReviewQueried' => 'Chiarimento',
        'ReviewApproved' => 'Approvato',
    ],
    'planned_duration' => [
        'label' => 'Durata prevista (HH:MM)',
        'hint' => 'Vuoto: durata della finestra oraria o dell’appuntamento. Vale come piano in Piano/effettivo, Analisi dei tipi di ordine e Capacità del personale.',
        'format' => 'Indichi la durata prevista in ore:minuti, ad es. 1:30.',
        'range' => 'La durata prevista deve essere compresa tra 0:01 e 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Previsto/effettivo',
        'no_plan' => 'senza previsione',
        'own_only' => 'solo i propri tempi',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Restituire l\'accettazione',
            'revert_invoiced' => 'Annullare la fatturazione',
            'submit_review' => 'Sottoporre a verifica',
            'withdraw_review' => 'Ritirare la sottomissione',
            'approve_review' => 'Approvare',
            'query_review' => 'Chiedere un chiarimento',
            'resubmit_review' => 'Sottoporre di nuovo',
            'approve_and_complete' => 'Approvare e chiudere',
        ],
        'reject' => [
            'title' => 'Restituire l\'accettazione',
            'legend' => 'Restituzione',
            'hint' => 'L\'ordine torna su «Pianificato» e può essere accettato da un\'altra persona. La motivazione resta nella cronologia.',
        ],
        'handover' => [
            'legend' => 'Collaudo',
            'accepted_by' => 'Collaudato da (nome presso il cliente)',
            'hint' => 'Il verbale non riporta la firma del cliente. Indichi la persona che ha collaudato il lavoro presso il cliente.',
        ],
        'accepted_final_by' => 'Collaudato da :name il :date',
        'invoiced_with' => 'Fatturato con :reference',
        'invoiced' => [
            'transfer_reference' => 'Trasferimento n. :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Ordine annullato: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'L\'ordine può essere chiuso solo quando nessun cronometro è in funzione. Ancora attivo: :names.',
        ],
    ],
    // Periodenaufträge mit interner Prüfung (Feature 198), Folgeperiode und Demo.
    'period' => [
        'label' => 'Periodo',
        'badge' => 'Periodo: :period',
        'placeholder' => '2026, 2026-H1, 2026-Q1, 2026-03',
        'hint' => 'Anno (2026), semestre (2026-H1), trimestre (2026-Q1) o mese (2026-03).',
        'invalid' => 'Indicare il periodo come anno (2026), semestre (2026-H1), trimestre (2026-Q1) o mese (2026-03).',
        'kind_mismatch' => 'Questo tipo di voce richiede un periodo del tipo: :kinds.',
    ],
    'product_group' => [
        'label' => 'Gruppo di prodotti',
        'invalid' => 'Questo gruppo di prodotti non esiste nella sua organizzazione.',
    ],
    'review' => [
        'required_badge' => 'Verifica obbligatoria',
        'reviewer_named' => 'Verifica di :name',
        'submitted_by' => 'Sottoposto da :name il :date',
        'reviewer_label' => 'Persona che verifica',
        'reviewer_any' => 'Chiunque abbia il diritto di verifica',
        'reviewer_hint' => 'Senza scelta verifica chiunque abbia il diritto «Verificare ordini» – mai chi sottopone.',
        'waive_action' => 'Disattivare la verifica',
        'require_action' => 'Richiedere la verifica',
        'waive_hint' => 'Senza verifica obbligatoria l’ordine viene chiuso direttamente. La modifica resta con il suo motivo nello storico e nel registro di audit.',
        'require_hint' => 'Con verifica obbligatoria l’ordine viene sottoposto a verifica prima della chiusura e approvato da una seconda persona.',
        'approve_complete_hint' => 'Qui l’approvazione è anche la chiusura: approva e chiude l’ordine in un solo passaggio.',
        'query' => [
            'title' => 'Chiarimento sulla verifica',
            'hint' => 'L’ordine torna con la sua domanda a chi lo elabora e poi viene sottoposto di nuovo. La domanda resta nello storico.',
            'legend' => 'Chiarimento',
            'question' => 'La sua domanda',
        ],
        'error' => [
            'own_submission' => 'Chi ha sottoposto un ordine a verifica non può approvarlo né chiedere un chiarimento.',
            'requirement_locked' => 'La verifica obbligatoria non si può più modificare nello stato «:status».',
            'question_required' => 'Un chiarimento richiede un testo.',
            'not_in_review' => 'L’ordine non è più in verifica.',
        ],
        'flash' => [
            'waived' => 'Verifica obbligatoria disattivata per questo ordine.',
            'required' => 'Verifica obbligatoria attivata per questo ordine.',
        ],
        'inbox_reference' => 'Sottoposto da :name il :date',
        'inbox_type' => 'Verifica ordine',
        'queue' => [
            'title' => 'Da verificare',
            'subtitle' => 'Ordini che può verificare – per scadenza, poi per data di sottomissione. Le proprie sottomissioni non compaiono qui.',
            'empty_title' => 'Niente da verificare',
            'empty_message' => 'Al momento nessun ordine attende la sua verifica.',
            'column' => [
                'order' => 'Ordine',
                'submitted' => 'Sottoposto',
                'actions' => 'Azioni',
            ],
        ],
        'widget' => [
            'title' => 'Da verificare',
            'description' => 'Ordini in attesa della sua verifica interna – per scadenza, poi per data di sottomissione.',
            'open_queue' => 'Tutti →',
        ],
    ],
    'follow_up' => [
        'action' => 'Creare il periodo successivo',
        'title' => 'Creare il periodo successivo :period',
        'submit' => 'Creare',
        'copied' => 'Vengono ripresi cliente, tipo di voce, gruppo di prodotti, responsabile, verifica obbligatoria e persona che verifica; le procedure partono come nuove esecuzioni. I promemoria aperti compaiono nel nuovo ordine.',
        'not_copied' => 'Non vengono ripresi contenuto e note, tempi e risultato.',
        'exists_hint' => 'Per :period questo ordine esiste già:',
        'content' => 'Periodo successivo :period dell’ordine n. :id.',
        'error' => [
            'no_series' => 'Il periodo successivo esiste solo per ordini con periodo, cliente e tipo di voce.',
            'exists' => 'L’ordine esiste già per questo periodo (n. :id).',
            'selection' => 'Scegliere tipo di voce e periodo.',
        ],
        'flash' => [
            'created' => 'Ordine per :period creato.',
            'bulk' => ':created ordini per :period creati, :skipped saltati.',
        ],
        'bulk' => [
            'title' => 'Periodo successivo in blocco',
            'subtitle' => 'Creare l’ordine del periodo successivo per tutti gli ordini di un tipo di voce e periodo – prima l’anteprima, poi la creazione.',
            'choose_title' => 'Scegliere tipo di voce e periodo',
            'choose_message' => 'L’anteprima mostra quali ordini verranno creati e quali esistono già.',
            'none_title' => 'Nessun ordine',
            'none_message' => 'Non ci sono ordini di questo tipo di voce in :period.',
            'summary' => ':count ordini in :from, di cui :creatable nuovi per :to.',
            'limit' => 'Si elaborano al massimo :limit ordini alla volta.',
            'submit' => 'Creare :count ordini',
            'column' => [
                'source' => 'Ordine',
                'target' => 'Nuovo periodo',
                'result' => 'Risultato',
            ],
            'result' => [
                'create' => 'verrà creato',
                'exists' => 'esiste già',
                'no_customer' => 'senza cliente',
                'duplicate_source' => 'doppio nell’anteprima',
            ],
        ],
    ],
    'demo' => [
        'entry_type' => 'Ordine annuale (demo)',
        'title' => 'Ordine annuale :year',
        'content' => 'Ordine annuale dimostrativo: raccogliere i documenti, elaborarli, sottoporli a verifica.',
        'approval_note' => 'Verificato, nessuna osservazione.',
        'summary' => 'Documenti completi, risultato trasmesso.',
        'carry_forward' => 'L’anno prossimo richiedere le ricevute entro fine febbraio.',
    ],
    'entry_type' => [
        'legend' => 'Periodo e verifica',
        'description' => 'Ordini periodici (anno, semestre, trimestre, mese) e verifica interna prima della chiusura.',
        'requires_period' => 'Periodo obbligatorio alla creazione',
        'requires_period_hint' => 'Senza scelta il periodo è facoltativo. Con più tipi va bene ciascuno.',
        'requires_review' => 'Verifica obbligatoria prima della chiusura (principio dei quattro occhi)',
    ],
];
