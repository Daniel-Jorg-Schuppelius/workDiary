<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : operations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Attività operative',
        'subtitle' => 'Aggiornamenti, backup, scadenze e guasti — con priorità e tracciabilità.',
        'widget' => 'Attività operative aperte',
    ],
    'type' => [
        'backup_overdue' => 'Backup in ritardo',
        'backup_failed' => 'Backup non riuscito',
        'restore_test_overdue' => 'Test di ripristino in ritardo',
        'update_available' => 'Aggiornamento disponibile',
        'update_security' => 'Aggiornamento di sicurezza',
        'license_expiring' => 'Scadenza licenza',
        'license_limit_near' => 'Limite utenti quasi raggiunto',
        'credential_expiring' => 'Scadenza credenziale/token',
        'connection_failing' => 'Connessione guasta',
        'component_eol' => 'Componente a fine vita',
        'plugin_disabled' => 'Plugin disattivato',
        'scheduler_overdue' => 'Attività pianificata in ritardo',
        'queue_failed_jobs' => 'Job in background falliti',
        'queue_worker_down' => 'Worker della coda inattivo',
        'maintenance_scheduled' => 'Finestra di manutenzione',
        'config_missing' => 'Configurazione mancante',
        'support_grant_open' => 'Autorizzazione di supporto aperta',
        'problem_report_open' => 'Segnalazione di problema aperta',
        'cloud_intake_reauth' => 'Ingresso cloud: nuovo accesso necessario',
        'cloud_intake_quarantined' => 'Ingresso cloud: importazioni rifiutate',
    ],
    'severity' => [
        'info' => 'Avviso',
        'warning' => 'Attenzione',
        'critical' => 'Critico',
    ],
    'status' => [
        'open' => 'Aperta',
        'snoozed' => 'Posticipata',
        'delegated' => 'Delegata',
        'ignored' => 'Ignorata',
        'done' => 'Completata',
        'resolved' => 'Risolta da sola',
    ],
    'field' => [
        'task' => 'Attività',
        'severity' => 'Gravità',
        'status' => 'Stato',
        'first_seen' => 'Rilevata il',
        'last_seen' => 'Confermata il',
        'assignee' => 'Responsabile',
        'actions' => 'Azioni',
        'note' => 'Motivazione',
        'snooze_until' => 'Posticipa fino al',
        'system_wide' => "A livello di installazione",
    ],
    'action' => [
        'done' => 'Completa',
        'snooze' => 'Posticipa',
        'delegate' => 'Delega',
        'ignore' => 'Ignora',
        'reopen' => 'Riapri',
        'open_link' => 'Vai alla causa',
    ],
    'task' => [
        'backup_overdue' => "L'ultimo backup risale a :hours ore fa (soglia :threshold h).",
        'backup_failed' => 'Controllo del backup non riuscito: :reason',
        'backup_target_failed' => 'Backup cloud fallito: :reason',
        'backup_target_verify_failed' => 'Verifica del backup cloud fallita: :reason',
        'restore_test_overdue' => "L'ultimo test di ripristino risale a :days giorni fa (soglia :threshold giorni).",
        'restore_test_missing' => 'Non è mai stato registrato un test di ripristino.',
        'update_available' => 'Aggiornamento disponibile per :component: :installed → :available.',
        'update_security' => 'Aggiornamento di sicurezza per :component: :installed → :available (:classification).',
        'license_expiring' => 'La licenza scade il :date (:days giorni rimanenti).',
        'license_limit_near' => ':org: :current di :max postazioni licenziate in uso — estendere la licenza per tempo.',
        'credential_expiring' => ':kind «:name» scade il :date.',
        'connection_failing' => 'Connessione «:name» (:kind) guasta: :error',
        'component_eol' => ':component :version non è più supportato dal :date.',
        'plugin_disabled' => 'Il plugin «:plugin» è stato disattivato automaticamente dopo :failures errori.',
        'scheduler_overdue' => "L'attività pianificata «:job» è in ritardo (scadenza :due).",
        'queue_failed_jobs' => ':count job in background falliti (ultimo :last) — controllare failed_jobs, queue:retry.',
        'queue_worker_down' => 'Il worker della coda non si segnala da :minutes minuti — controllare servizio/cron.',
        'maintenance_scheduled' => 'Finestra di manutenzione :from – :to::scope',
        'support_grant_open' => 'Autorizzazione di supporto per :grantee attiva fino al :until.',
        'problem_report_open' => 'La segnalazione :reference di :name è in attesa di elaborazione.',
        'problem_report_summary' => ':count segnalazione/i aperta/e in attesa di elaborazione.',
        'cloud_intake_reauth' => 'L’ingresso documenti cloud :provider (“:folder”) deve essere ricollegato (:status).',
        'cloud_intake_quarantined' => ':count file dall’ingresso documenti cloud sono stati rifiutati (ultimo motivo: :reason).',
        'support_grant_summary' => ':count autorizzazione/i di supporto attiva/e — verificare ed eventualmente revocare.',
    ],
    'filter' => [
        'active' => 'Attività attive',
        'all_severities' => 'Tutte le gravità',
        'all_types' => 'Tutti i tipi',
    ],
    'empty' => [
        'title' => 'Nessuna attività operativa',
        'message' => 'Niente da fare al momento — tutte le attività operative sono completate o risolte da sole.',
    ],
    'hint' => [
        'auto_disabled_after' => 'Disattivato automaticamente dopo :failures tentativi falliti.',
        'no_contact_since' => 'Nessun contatto dal :date.',
    ],
    'flash' => [
        'done' => 'Attività contrassegnata come completata.',
        'snoozed' => 'Attività posticipata fino al :date.',
        'delegated' => 'Attività delegata.',
        'ignored' => 'Attività ignorata.',
        'reopened' => 'Attività riaperta.',
    ],
    'widget' => [
        'open' => 'Attività aperte',
        'empty' => 'Nessuna attività operativa aperta.',
        'all' => 'Mostra tutte le attività operative',
    ],
];
