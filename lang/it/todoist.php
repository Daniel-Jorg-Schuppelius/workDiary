<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : todoist.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'subtitle' => 'Sincronizzazione delle attività con Todoist — solo progetti esplicitamente associati, conflitti tramite la inbox di integrazione.',
    'task_link' => 'Apri in Todoist',

    'connection' => [
        'title' => 'Connessione',
        'none' => 'Nessuna connessione Todoist. Viene stabilita esattamente una connessione per organizzazione.',
        'privacy_note' => 'Con la connessione, titoli, descrizioni, stati, scadenze e assegnatari delle attività associate vengono trasmessi a Todoist e letti da lì. Gli scope di eliminazione non vengono richiesti.',
        'connect' => 'Connetti a Todoist',
        'reconnect' => 'Rinnova connessione',
        'disconnect' => 'Disconnetti',
        'confirm_disconnect' => 'Disconnettere? Associazioni e riferimenti vengono conservati.',
        'account' => 'Account',
        'connected_at' => 'Connesso da',
        'last_sync' => 'Ultima sincronizzazione',
        'sync_now' => 'Sincronizza ora',
        'open_inbox' => 'Inbox di integrazione',
    ],

    'status' => [
        'active' => 'Attiva',
        'paused' => 'In pausa',
        'disconnected' => 'Disconnessa',
    ],

    'links' => [
        'title' => 'Associazioni progetti',
        'empty' => 'Ancora nessuna associazione di progetto.',
        'add' => 'Associa',
        'hint' => 'Le nuove associazioni partono come bozza — attivazione solo dopo il preflight (nessun import completo non presidiato).',
        'global_kanban' => 'Kanban globale',
        'target_project' => 'Progetto WorkDiary',
        'workdiary_project' => 'Progetto WorkDiary',
        'preflight' => 'Preflight',
        'activate' => 'Attiva',
        'pause' => 'Metti in pausa',
        'remove' => 'Rimuovi',
        'confirm_remove' => 'Rimuovere l\'associazione? I riferimenti vengono conservati.',
        'col' => [
            'todoist_project' => 'Progetto Todoist',
            'target' => 'Destinazione',
            'mode' => 'Direzione',
            'last_run' => 'Ultima esecuzione',
            'actions' => 'Azioni',
        ],
    ],

    'mode' => [
        'todoist_to_workdiary' => 'Todoist → WorkDiary',
        'workdiary_to_todoist' => 'WorkDiary → Todoist',
        'bidirectional' => 'Bidirezionale',
    ],

    'link_status' => [
        'draft' => 'Bozza',
        'active' => 'Attiva',
        'paused' => 'In pausa',
    ],

    'preflight' => [
        'title' => 'Preflight',
        'counters' => 'Indicatori',
        'tasks' => 'Attività attive',
        'subtasks' => 'Sottoattività',
        'recurring' => 'Ricorrenti',
        'timed_due' => 'Scadenza con orario',
        'unassignable' => 'Assegnatari non associabili',
        'referenced' => 'Già referenziate',
        'hint' => 'Le attività ricorrenti e le scadenze con orario vengono riprese solo in modalità lettura guidata da Todoist. Predefinito: “associa solo l\'esistente”.',
        'collaborators' => 'Associazione assegnatari',
        'suggestion' => 'Suggerimento',
        'unassign' => '— dissocia —',
        'no_collaborators' => 'Nessun collaboratore trovato.',
        'sections' => 'Sezioni → stato',
        'no_sections' => 'Questo progetto non ha sezioni.',
        'section_unmapped' => '— non associata (stato invariato) —',
        'section_open' => 'Aperta',
        'section_in_progress' => 'In corso',
        'col' => [
            'collaborator' => 'Collaboratore Todoist',
            'email' => 'E-mail',
            'mapped' => 'Associato',
            'assign' => 'Associa',
        ],
    ],

    'flash' => [
        'not_configured' => 'Todoist non è configurato (TODOIST_CLIENT_ID/SECRET mancanti).',
        'state_invalid' => 'Stato OAuth non valido o scaduto — riconnettersi.',
        'oauth_denied' => 'L\'autorizzazione è stata annullata.',
        'oauth_failed' => 'Scambio del token non riuscito (:class).',
        'connected' => 'Todoist connesso.',
        'disconnected' => 'Connessione disconnessa.',
        'link_saved' => 'Associazione salvata.',
        'link_removed' => 'Associazione rimossa.',
        'link_project_required' => 'Selezionare un progetto WorkDiary.',
        'no_connection' => 'Nessuna connessione Todoist attiva.',
        'sync_done' => 'Sincronizzazione completa avviata.',
        'preflight_failed' => 'Preflight non riuscito (:class).',
        'sections_saved' => 'Associazioni delle sezioni salvate.',
        'collaborator_assigned' => 'Assegnatario associato.',
        'collaborator_unassigned' => 'Associazione rimossa.',
        'collaborator_invalid' => 'Utente non valido.',
    ],
];
