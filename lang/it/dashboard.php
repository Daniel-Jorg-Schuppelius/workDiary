<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dashboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'width' => [
        'half' => 'Metà larghezza',
        'full' => 'Larghezza intera',
    ],

    'group' => [
        'overview' => 'Panoramica',
        'time' => 'Tempo',
        'tasks' => 'Attività',
        'activity' => 'Attività recenti',
        'deadlines' => 'Scadenze',
        'finance' => 'Finanze',
        'operations' => 'Esercizio',
    ],

    'widget' => [
        'personal_kpis' => [
            'description' => 'Voci aperte, lavori in corso, turni e reperibilità in arrivo.',
        ],
        'team_kpis' => [
            'description' => 'Voci aperte e in corso del team, archiviate oggi, numero di collaboratori.',
        ],
        'today_shifts' => [
            'description' => 'I tuoi turni di oggi.',
        ],
        'upcoming_shifts' => [
            'description' => 'Le tue prossime reperibilità e turni.',
        ],
        'emergencies' => [
            'description' => 'Interventi di reperibilità in arrivo.',
        ],
        'scheduled_shifts' => [
            'description' => 'Piano turni dei prossimi sette giorni.',
        ],
        'open_issues' => [
            'description' => 'Punti aperti assegnati a te — per scadenza.',
        ],
        'recent_entries' => [
            'description' => 'Le tue voci modificate di recente.',
        ],
        'recent_comments' => [
            'description' => 'Nuovi commenti sulle tue voci.',
        ],
        'recent_attachments' => [
            'description' => 'Nuovi allegati sulle tue voci.',
        ],
        'team_activity' => [
            'description' => 'Gli ultimi commenti del team.',
        ],
        'finance' => [
            'description' => 'Spese e trasferte del mese, per gli approvatori anche la coda in attesa.',
        ],
        'vacation' => [
            'description' => 'Richieste di ferie aperte e giorni approvati nell\'anno.',
        ],
        'onboarding' => [
            'description' => 'Avanzamento della lista di configurazione.',
        ],
        'attendance_clock' => [
            'description' => 'Timbratura di entrata e uscita, pause e stati intermedi.',
        ],
        'bookmarks' => [
            'description' => 'I tuoi segnalibri salvati.',
        ],
        'data_protection' => [
            'description' => 'Revisioni del registro scadute e richieste degli interessati aperte.',
        ],
        'operations_tasks' => [
            'description' => 'Attività di esercizio aperte per urgenza.',
        ],
        'stopwatch' => [
            'description' => 'Il cronometro in corso con progetto e descrizione.',
        ],
        'flex_balance' => [
            'description' => 'Saldo dell\'orario flessibile dell\'ultimo mese chiuso, con semaforo.',
        ],
        'time_accounts' => [
            'description' => 'Saldi dei tuoi conti ore (straordinari, conti speciali).',
        ],
        'time_corrections' => [
            'description' => 'Le tue richieste di correzione ancora in lavorazione o inviate.',
        ],
        'reminders' => [
            'description' => 'Cose da fare da spese, trasferte e ferie — le stesse della campanella.',
        ],
        'kanban_status' => [
            'description' => 'Quanti dei tuoi ordini si trovano in ciascuna colonna Kanban.',
        ],
        'service_tickets' => [
            'description' => 'Ticket aperti assegnati a te.',
        ],
        'chat_unread' => [
            'description' => 'Messaggi non letti per canale.',
        ],
        'approvals' => [
            'description' => 'Spese e richieste di ferie in attesa della tua decisione.',
        ],
        'asset_compliance' => [
            'description' => 'Verifiche scadute e in scadenza dal calendario dei controlli.',
        ],
        'asset_blocks' => [
            'description' => 'Oggetti attualmente bloccati, con il motivo.',
        ],
        'contract_deadlines' => [
            'description' => 'Obblighi e scadenze contrattuali delle prossime settimane.',
        ],
        'leasing_deadlines' => [
            'description' => 'Scadenze di disdetta, restituzione e rinnovo dai fascicoli di leasing.',
        ],
        'safety_due' => [
            'description' => 'Revisioni in scadenza delle valutazioni dei rischi e delle visite mediche.',
        ],
        'training_due' => [
            'description' => 'I tuoi obblighi formativi e di istruzione aperti.',
        ],
        'open_times' => [
            'description' => 'Tempi fatturabili non ancora inseriti in una fattura.',
        ],
        'open_items' => [
            'description' => 'Crediti e debiti aperti, inclusa la quota scaduta.',
        ],
        'tax_filings' => [
            'description' => 'Prossime scadenze di dichiarazione in contabilità.',
        ],
        'integration_inbox' => [
            'description' => 'Voci importate ancora da abbinare.',
        ],
        'backup_status' => [
            'description' => 'Quanto sono recenti i backup, per fonte.',
        ],
        'plugin_health' => [
            'description' => 'Plugin il cui ultimo controllo di stato è fallito.',
        ],
    ],

    'preset' => [
        'classic' => [
            'label' => 'Dashboard classica',
            'description' => 'Indicatori e segnalibri in alto, sotto le quattro sezioni Panoramica, Attività, Attività recenti e Finanze — la dashboard com’era prima della conversione in schede, più l’orologio marcatempo.',
        ],
    ],
];
