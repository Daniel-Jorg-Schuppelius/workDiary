<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : crisis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Offline-Krisenmappe (MVP-914).
    'offline' => [
        'save' => 'Salvare la cartella di crisi offline',
        'hint' => 'Salva su questo dispositivo le crisi attive con situazione, misure e recapiti dell\'unità di crisi; leggibile senza connessione nella pagina offline. Eliminata al logout.',
    ],
    // Öffentliche Statusseite (MVP-915).
    'status_page' => [
        'title' => 'Pagina di stato (pubblica)',
        'intro' => 'La pagina di stato mostra senza accesso i messaggi di crisi inviati al pubblico; i messaggi ai clienti compaiono anche nel portale clienti. Vengono mostrati solo oggetto, testo e ora, mai il fascicolo di crisi.',
        'token_once' => 'Questo link viene mostrato solo ora — non è memorizzato da nessuna parte.',
        'state' => 'Stato',
        'state_none' => 'Non configurato',
        'state_active' => 'Raggiungibile pubblicamente',
        'state_paused' => 'In pausa',
        'hint' => 'Identificativo',
        'issued_at' => 'Emesso il',
        'action' => [
            'issue' => 'Emetti link',
            'rotate' => 'Rinnova link',
            'revoke' => 'Revoca accesso',
            'pause' => 'Sospendi',
            'resume' => 'Attiva',
        ],
        'confirm' => [
            'rotate' => 'Emettere un nuovo link? Quello precedente smetterà di funzionare.',
            'revoke' => 'Revocare l\'accesso? La pagina di stato non sarà più raggiungibile pubblicamente.',
        ],
        'flash' => [
            'issued' => 'Nuovo link emesso.',
            'revoked' => 'Accesso revocato.',
            'saved' => 'Salvato.',
        ],
        'public_title' => 'Situazione attuale – :org',
        'public_intro' => 'Qui vi informiamo su disservizi e incidenti in corso.',
        'all_clear' => 'Al momento non ci sono disservizi.',
        'resolved' => 'cessato allarme',
    ],
    // BIA-Register (MVP-943).
    'bia' => [
        'title' => 'Registro BIA',
        'subtitle' => 'Processi aziendali con criticità, obiettivi di ripristino (RTO/RPO) e interruzione massima tollerabile (MTPD).',
        'create' => 'Aggiungi processo',
        'edit' => 'Modifica processo',
        'save' => 'Salva',
        'empty' => 'Nessun processo nel registro.',
        'inactive' => 'inattivo',
        'import' => 'Importa dai registri',
        'import_hint' => 'Proposte dal registro dei trattamenti, dai rischi ISMS e dai modelli di procedura attivi. Viene importato solo ciò che seleziona.',
        'import_submit' => 'Importa selezione',
        'adopt' => 'Adotta dal registro BIA',
        'kind' => [
            'processing_activity' => 'Trattamento',
            'isms_risk' => 'Rischio ISMS',
            'procedure_template' => 'Modello di procedura',
        ],
        'criticality' => [
            'low' => 'bassa',
            'medium' => 'media',
            'high' => 'alta',
            'critical' => 'critica',
        ],
        'field' => [
            'name' => 'Processo',
            'description' => 'Descrizione',
            'criticality' => 'Criticità',
            'rto_hours' => 'RTO (ore)',
            'rpo_hours' => 'RPO (ore)',
            'mtpd_hours' => 'MTPD (ore)',
            'owner' => 'Responsabile',
            'dependencies' => 'Dipendenze (sistemi, fornitori, persone)',
            'review_due_on' => 'Revisione prevista',
            'is_active' => 'Attivo',
        ],
        'flash' => [
            'saved' => 'Processo salvato.',
            'imported' => ':count processi importati.',
            'adopted' => 'Processo adottato dal registro BIA.',
        ],
    ],
    // BCM-Auswertung (MVP-944).
    'bcm_report' => [
        'title' => 'Rapporto BCM',
        'subtitle' => 'Indicatori secondo ISO 22301: esercitazioni, azioni, riesami e stato BIA.',
        'back' => 'Gestione delle crisi',
        'disclaimer' => 'Indicatori dai dati registrati; nessuna dichiarazione sulla certificabilità.',
        'overdue' => ':count scadute',
        'row' => [
            'exercises' => 'Esercitazioni dal :date',
            'effectiveness' => 'Efficacia',
            'exercises_due' => 'Esercitazioni scadute',
            'actions_open' => 'Azioni aperte',
            'reviews' => 'Crisi concluse con riesame',
            'processes' => 'Processi nel registro BIA',
            'without_rto' => 'Processi senza RTO',
            'review_due' => 'Processi con revisione scaduta',
        ],
        'effectiveness' => [
            'effective' => 'efficace',
            'partly' => 'parzialmente',
            'ineffective' => 'inefficace',
            'open' => 'non valutato',
        ],
    ],
    // Krisenraum (MVP-963).
    'room' => [
        'title' => 'Sala di crisi',
        'present' => 'Presenti',
        'nobody' => 'nessun altro',
        'no_markers' => 'Nessun luogo: colleghi asset o clienti con coordinate o aggiunga punti.',
        'add_point' => 'Aggiungi punto',
        'field' => [
            'label' => 'Denominazione',
            'kind' => 'Tipo',
            'lat' => 'Latitudine',
            'lng' => 'Longitudine',
        ],
        'kind' => [
            'incident' => 'Luogo dell’incidente',
            'assembly' => 'Punto di raccolta',
            'closure' => 'Chiusura',
            'resource' => 'Risorsa',
            'other' => 'Altro',
        ],
        'layer' => [
            'linked' => 'Oggetti collegati',
        ],
        'link' => [
            'asset' => 'Asset',
            'customer' => 'Cliente',
        ],
        'flash' => [
            'point_saved' => 'Punto aggiunto.',
            'point_deleted' => 'Punto rimosso.',
        ],
    ],
    // Meldefristen je Organisation (MVP-1589).
    'deadlines' => [
        'title' => 'Termini di notifica',
        'subtitle' => 'Termini per categoria di crisi. I termini propri di una categoria sostituiscono i valori predefiniti; le scadenze di un fascicolo di crisi decorrono dall’attivazione.',
        'create' => 'Aggiungere un termine di notifica',
        'edit' => 'Modificare il termine di notifica',
        'save' => 'Salvare',
        'defaults' => 'Valori predefiniti',
        'empty' => 'Nessun termine proprio. Valgono i valori predefiniti.',
        'no_defaults' => 'Nessun valore predefinito.',
        'inactive' => 'inattivo',
        'replaced' => 'sostituito',
        'immediate' => 'senza indugio',
        'adopt' => 'Adottare come termini propri',
        'activate' => 'Attivare',
        'deactivate' => 'Disattivare',
        'form_hint' => 'Non appena una categoria ha un termine proprio attivo, per essa valgono solo i termini propri. Adotti prima i valori predefiniti se desidera modificare un solo termine.',
        'field' => [
            'category' => 'Categoria',
            'label' => 'Denominazione',
            'offset_hours' => 'Termine (ore)',
            'offset_hours_hint' => 'Ore dall’attivazione della crisi. Vuoto = senza indugio.',
            'source' => 'Base giuridica',
            'active' => 'Attivo',
        ],
        'flash' => [
            'saved' => 'Termine di notifica salvato.',
            'adopted' => 'Valori predefiniti adottati come termini propri.',
            'already_own' => 'Questa categoria ha già termini propri.',
            'activated' => 'Termine di notifica attivato.',
            'deactivated' => 'Termine di notifica disattivato.',
        ],
    ],
    // Abhängigkeiten, Ersatzprozess, Anwesenheitsliste, Eröffnen aus Vorfall (MVP-1605).
    'link' => [
        'dialog_title' => 'Collega una pratica',
        'target' => 'Pratica',
        'target_hint' => 'Per ogni tipo sono proposte le pratiche più recenti che Lei può leggere.',
        'note' => 'Nota sul collegamento',
        'none_available' => 'Non ci sono pratiche che Lei può collegare.',
        'opened_from' => 'Crisi aperta da questo incidente',
        'type' => [
            'service_ticket' => 'Ticket di assistenza',
            'isms_incident' => 'Incidente di sicurezza',
            'privacy_incident' => 'Incidente di protezione dei dati',
            'safety_event' => 'Evento di sicurezza sul lavoro',
            'procedure_run' => 'Esecuzione di playbook/procedura',
            'document' => 'Documento',
            'asset' => 'Asset',
            'customer' => 'Cliente',
        ],
    ],
    'open' => [
        'action' => 'Apri crisi',
        'from' => 'La crisi viene aperta da :source e collegata all’incidente.',
    ],
    'action' => [
        'depends_on' => 'Predecessore',
        'no_predecessor' => 'senza predecessore',
        'after' => 'dopo: :title',
        'blocked_by' => 'La misura può essere completata solo quando «:title» è conclusa o annullata.',
        'depends_foreign' => 'Il predecessore non appartiene a questa pratica di crisi.',
        'depends_cycle' => 'Il predecessore attende a sua volta questa misura: si creerebbe un ciclo.',
        'predecessor_saved' => 'Predecessore salvato.',
    ],
    'bcm' => [
        'substitute_process' => 'Processo sostitutivo',
        'residual_note' => 'Limitazione residua',
    ],
    'presence' => [
        'open' => 'Elenco presenze di emergenza',
    ],
];
