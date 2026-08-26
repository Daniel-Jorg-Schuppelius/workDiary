<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Eventi di sicurezza',
    ],
    'subtitle' => [
        'index' => 'Registrare e monitorare infortuni, quasi infortuni, pericoli e difetti.',
    ],
    'empty' => 'Nessun evento di sicurezza registrato.',

    'field' => [
        'event_no' => 'N.',
        'kind' => 'Tipo',
        'severity' => 'Gravità',
        'status' => 'Stato',
        'occurred_at' => 'Avvenuto il',
        'location' => 'Luogo',
        'affected_person' => 'Persona coinvolta',
        'reporter' => 'Segnalato da',
        'subject' => 'Collegato a',
        'description' => 'Descrizione',
        'immediate_action' => 'Azione immediata',
        'root_cause' => 'Analisi delle cause',
        'closed_at' => 'Chiuso il',
        'closed_by' => 'Chiuso da',
        'followup_title' => 'Titolo della misura di follow-up',
        'followup_description' => 'Descrizione (facoltativa)',
    ],

    'section' => [
        'status' => 'Cambia stato',
        'followup' => 'Misura di follow-up',
        'attachments' => 'Allegati',
        'followups' => 'Misure di follow-up',
    ],

    'no_attachments' => 'Nessun allegato.',
    'no_followups' => 'Ancora nessuna misura di follow-up.',

    'action' => [
        'create' => 'Segnala evento',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'show' => 'Visualizza',
        'back' => 'Indietro',
        'create_followup' => 'Crea follow-up',
    ],

    'transition' => [
        'investigating' => 'Avvia indagine',
        'measuresDefined' => 'Misure definite',
        'closed' => 'Chiudi',
    ],

    'hint' => [
        'root_cause_for_close' => 'Per chiudere l’evento è necessaria un’analisi delle cause.',
        'followup' => 'Crea un punto aperto come rilavorazione collegato a questo evento.',
    ],

    'flash' => [
        'created' => 'Evento di sicurezza registrato.',
        'updated' => 'Evento di sicurezza aggiornato.',
        'deleted' => 'Evento di sicurezza eliminato.',
        'followup_created' => 'Misura di follow-up creata.',
        'status' => [
            'reported' => 'Evento reimpostato.',
            'investigating' => 'Indagine avviata.',
            'measuresDefined' => 'Misure definite.',
            'closed' => 'Evento chiuso.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Cambio di stato non valido: :from → :to.',
        'close_requires_root_cause' => 'La chiusura richiede un’analisi delle cause.',
    ],

    'report' => [
        'title' => 'Analisi della sicurezza',
        'nav' => 'Sicurezza sul lavoro',
        'subtitle' => 'Eventi di sicurezza per tipo e gravità nel periodo.',
        'by_kind' => 'Per tipo',
        'by_severity' => 'Per gravità',
        'kpi' => [
            'total' => 'Eventi totali',
            'open' => 'Aperti',
            'closed' => 'Chiusi',
            'critical' => 'Critici',
        ],
    ],

    // Registro sicurezza sul lavoro (Feature 132): valutazione dei rischi, formazione, sorveglianza sanitaria.
    'register' => [
        'section' => 'Sicurezza sul lavoro',
        'nav' => [
            'assessments' => 'Valutazioni dei rischi',
            'instructions' => 'Formazioni',
            'checkups' => 'Visite mediche',
        ],
        'title' => [
            'assessments' => 'Valutazioni dei rischi',
            'instructions' => 'Formazioni sulla sicurezza',
            'checkups' => 'Sorveglianza sanitaria',
        ],
        'subtitle' => [
            'assessments' => 'Valutazioni dei rischi secondo § 5 ArbSchG — versionate, con data di revisione.',
            'instructions' => 'Formazioni secondo DGUV Vorschrift 1 § 4 con prova di partecipazione per persona.',
            'checkups' => 'Sorveglianza sanitaria secondo ArbMedVV — solo tipo, data e certificato, nessun dato sanitario.',
        ],
        'field' => [
            'assessment_no' => 'Numero',
            'version' => 'Versione',
            'area' => 'Area',
            'activity' => 'Attività',
            'description' => 'Descrizione',
            'status' => 'Stato',
            'review_due_on' => 'Revisione prevista',
            'approved_by' => 'Approvato da',
            'approved_at' => 'Approvato il',
            'created_by' => 'Creato da',
            'supersedes' => 'Sostituisce',
            'superseded_by' => 'Sostituito da',
            'items' => 'Pericoli',
            'position' => 'Pos.',
            'hazard' => 'Pericolo',
            'measure' => 'Misura',
            'severity' => 'Gravità (G)',
            'likelihood' => 'Probabilità (P)',
            'risk_before' => 'Rischio prima',
            'risk_after' => 'Rischio dopo',
            'before' => 'Prima della misura',
            'after' => 'Dopo la misura',
            'instruction_no' => 'Numero',
            'topic' => 'Argomento',
            'held_on' => 'Data',
            'instructor' => 'Formatore/trice',
            'assessment' => 'Valutazione dei rischi',
            'repeat_interval_months' => 'Ripetizione (mesi)',
            'notes' => 'Note',
            'participants' => 'Partecipanti',
            'signed' => 'Confermato',
            'signed_at' => 'Confermato il',
            'method' => 'Forma di prova',
            'next_due_on' => 'Prossima scadenza',
            'user' => 'Persona',
            'kind' => 'Tipo',
            'occasion' => 'Motivo',
            'performed_on' => 'Eseguita il',
            'certificate_on_file' => 'Certificato disponibile',
        ],
        'action' => [
            'create_assessment' => 'Crea valutazione dei rischi',
            'edit' => 'Modifica',
            'save' => 'Salva',
            'delete' => 'Elimina',
            'show' => 'Visualizza',
            'back' => 'Indietro',
            'transition' => 'Cambia stato',
            'new_version' => 'Crea versione successiva',
            'add_item' => 'Aggiungi pericolo',
            'edit_item' => 'Modifica pericolo',
            'create_instruction' => 'Registra formazione',
            'sign' => 'Conferma partecipazione',
            'create_checkup' => 'Registra visita',
        ],
        'filter' => [
            'all' => 'Tutti',
            'current_only' => 'Solo versioni attuali',
            'open_only' => 'Solo con conferme aperte',
            'due_only' => 'Solo scadute',
        ],
        'kpi' => [
            'review_due' => 'Revisione scaduta',
            'instruction_due' => 'Ripetizione scaduta',
            'checkup_due' => 'Visita scaduta',
        ],
        'empty' => [
            'assessments' => 'Nessuna valutazione dei rischi ancora creata.',
            'items' => 'Nessun pericolo ancora registrato.',
            'instructions' => 'Nessuna formazione ancora registrata.',
            'participants' => 'Nessun partecipante.',
            'checkups' => 'Nessuna visita ancora registrata.',
        ],
        'hint' => [
            'frozen' => 'Questa versione è approvata e congelata. Le modifiche avvengono tramite una versione successiva.',
            'approve_requires_items' => 'L’approvazione richiede almeno un pericolo.',
            'sign_self' => 'Conferma la tua partecipazione — nome, ora e indirizzo IP vengono registrati come prova.',
            'no_health_data' => 'Non vengono memorizzati referti o diagnosi — solo tipo, data e se il certificato è disponibile.',
            'after_optional' => 'Rischio dopo la misura facoltativo — inserire entrambi i valori insieme.',
            'pdf_not_in_mvp' => 'La prova in PDF seguirà in una fase successiva.',
        ],
        'confirm' => [
            'delete_assessment' => 'Eliminare la valutazione dei rischi?',
            'delete_item' => 'Eliminare il pericolo?',
            'delete_instruction' => 'Eliminare la formazione?',
            'delete_checkup' => 'Eliminare la voce della visita?',
            'sign' => 'Confermare ora la partecipazione (vincolante)?',
        ],
        'flash' => [
            'assessment_created' => 'Valutazione dei rischi creata.',
            'assessment_updated' => 'Valutazione dei rischi aggiornata.',
            'assessment_transitioned' => 'Stato modificato.',
            'assessment_version_created' => 'Versione successiva :version creata.',
            'assessment_deleted' => 'Valutazione dei rischi eliminata.',
            'item_created' => 'Pericolo aggiunto.',
            'item_updated' => 'Pericolo aggiornato.',
            'item_deleted' => 'Pericolo rimosso.',
            'instruction_created' => 'Formazione registrata.',
            'instruction_updated' => 'Formazione aggiornata.',
            'instruction_deleted' => 'Formazione eliminata.',
            'participation_signed' => 'Partecipazione confermata.',
            'checkup_created' => 'Visita registrata.',
            'checkup_updated' => 'Visita aggiornata.',
            'checkup_deleted' => 'Voce della visita eliminata.',
        ],
        'error' => [
            'assessment_frozen' => 'Le valutazioni approvate sono congelate — creare una versione successiva.',
            'approve_requires_items' => 'L’approvazione richiede almeno un pericolo.',
            'new_version_requires_approved' => 'Una versione successiva è possibile solo da una versione approvata.',
            'after_pair_incomplete' => 'Rischio dopo la misura: inserire gravità e probabilità insieme.',
            'sign_only_self' => 'Solo la persona registrata può confermare la propria partecipazione.',
            'already_signed' => 'La partecipazione è già confermata.',
            'delete_with_signatures' => 'Le formazioni con prove confermate non possono essere eliminate.',
        ],
        'status_summary' => ':signed di :total confermati',
    ],
];
