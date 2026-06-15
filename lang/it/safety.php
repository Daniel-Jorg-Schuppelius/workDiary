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
];
