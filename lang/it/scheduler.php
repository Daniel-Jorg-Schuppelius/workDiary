<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scheduler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Attività pianificate',
        'subtitle' => 'Sospendere, ripianificare e monitorare i job del registro — senza modifiche al codice.',
        'help' => 'Solo job registrati, solo orari consentiti',
        'help_text' => 'Tutti i job provengono dal registro lato server. La ripianificazione è limitata agli intervalli consentiti per job; le modifiche sono verificate e hanno effetto al prossimo tick dello scheduler.',
        'reschedule' => 'Ripianifica job',
    ],
    'field' => [
        'job' => 'Job',
        'plan' => 'Pianificazione',
        'last_run' => 'Ultima esecuzione',
        'next_due' => 'Prossima scadenza',
        'failures' => 'Errori consecutivi',
        'actions' => 'Azioni',
        'cadence_type' => 'Intervallo',
        'time' => 'Ora',
        'day' => 'Giorno',
        'expression' => 'Espressione cron',
    ],
    'action' => [
        'reschedule' => 'Ripianifica',
        'pause' => 'Sospendi',
        'resume' => 'Riprendi',
        'reset' => 'Ripristina predefinito',
        'test_run' => 'Avvia esecuzione di prova',
        'save' => 'Salva',
    ],
    'state' => [
        'paused' => 'Sospeso',
        'success' => 'Riuscito',
        'failed' => 'Non riuscito',
        'never_ran' => 'Mai eseguito',
    ],
    'source' => [
        'default' => 'Piano predefinito',
        'setting' => 'Da impostazione',
        'override' => 'Ripianificato manualmente',
    ],
    'cadence' => [
        'everyMinute' => 'Ogni minuto',
        'everyFiveMinutes' => 'Ogni 5 minuti',
        'everyFifteenMinutes' => 'Ogni 15 minuti',
        'everyThirtyMinutes' => 'Ogni 30 minuti',
        'hourly' => 'Ogni ora',
        'dailyAt' => 'Ogni giorno alle',
        'weeklyOn' => 'Ogni settimana il',
        'monthlyOn' => 'Ogni mese il',
        'cron' => 'Espressione cron',
    ],
    'criticality' => [
        'core' => 'Operatività centrale',
        'integration' => 'Integrazione',
        'housekeeping' => 'Pulizia',
    ],
    // Nomi leggibili dei job (chiavi registry, annidate per via della notazione a punti);
    // aggiungere i nuovi job qui in tutte le lingue — altrimenti fallback = chiave.
    'job' => [
        'calendly' => ['backfill' => 'Sincronizzazione appuntamenti Calendly'],
        'ai' => ['maintenance' => 'Manutenzione IA (stato dei provider, pulizia dei suggerimenti)'],
        'archive' => ['run' => 'Esecuzione archiviazione'],
        'attendance' => ['close_open' => 'Chiudere le timbrature dimenticate'],
        'audit' => ['verify' => 'Verificare la catena di audit'],
        'backup' => [
            'check_restore' => 'Verifica dei backup',
            'cloud-run' => 'Esecuzione del backup cloud',
            'cloud-verify' => 'Verifica del backup cloud',
        ],
        'billbee' => ['sync' => 'Sincronizzazione Billbee'],
        'carddav' => ['sync' => 'Sincronizzazione CardDAV'],
        'catalog' => ['fetch_due' => 'Recuperare le fonti di catalogo'],
        'chat' => [
            'send_reminders' => 'Inviare i promemoria chat',
            'send_scheduled' => 'Inviare i messaggi chat pianificati',
        ],
        'claims' => ['escalate' => 'Escalation delle scadenze dei reclami'],
        'cloud-intake' => ['sync' => 'Recuperare la ricezione documenti cloud'],
        'compliance' => ['scan_findings' => 'Scansione dei rilievi di conformità'],
        'events' => [
            'check_certificates' => 'Verificare la scadenza dei certificati',
            'dispatch_reminders' => 'Inviare i promemoria eventi',
            'materialize_recurrences' => 'Materializzare gli eventi ricorrenti',
        ],
        'domain' => [
            'sync' => 'Sincronizzazione domini',
            'events' => 'Recupero eventi dominio',
        ],
        'easybill' => ['sync' => 'Recupero documenti easybill'],
        'integration' => ['purge_inbox' => 'Ripulire la inbox delle integrazioni'],
        'inventory' => ['cycle_counts' => 'Avvio inventario ciclico', 'expiring_lots' => 'Monitoraggio TMC (lotti in scadenza)'],
        'invoicing' => ['recurring' => 'Generare bozze di fatture ricorrenti'],
        'jtl' => ['sync' => 'Sincronizzazione JTL Wawi'],
        'lexoffice' => [
            'sync_articles' => 'Sincronizzare gli articoli Lexoffice',
            'sync_contacts' => 'Sincronizzare i contatti Lexoffice',
            'sync_vouchers' => 'Sincronizzare i documenti Lexoffice',
        ],
        'location' => ['purge_points' => 'Ripulire i punti di posizione grezzi'],
        'mail' => ['poll' => 'Recupero posta in arrivo'],
        'maintenance' => ['scan_due' => 'Controllare i piani di manutenzione in scadenza'],
        'notifications' => ['scan_deadlines' => 'Controllare le scadenze e notificare'],
        'openproject' => [
            'import' => 'Import OpenProject',
            'push' => 'Trasferire i tempi a OpenProject',
        ],
        'operations' => ['scan' => 'Sincronizzare le attività operative'],
        'orgamax' => ['sync' => 'Sincronizzazione orgaMAX'],
        'payroll' => ['import_minimum_wages' => 'Importare i salari minimi UE'],
        'plans' => ['purge' => 'Eliminare i dati dei moduli retrocessi'],
        'plugin' => ['healthcheck' => 'Controllo di integrità dei plugin'],
        'privacy' => [
            'deadlines' => 'Controllare le scadenze delle richieste degli interessati',
            'retention_scan' => 'Scansione dei termini di conservazione',
        ],
        'recurrence' => ['generate' => 'Generare gli ordini ricorrenti'],
        'remote' => ['sync_sessions' => 'Importare le sessioni di assistenza remota'],
        'scheduler' => ['watchdog' => 'Sorveglianza dello scheduler'],
        'security' => ['advisories_pull' => 'Recuperare gli avvisi di sicurezza'],
        'tickets' => ['scan_sla_breaches' => 'Rilevare le violazioni SLA'],
        'todoist' => ['sync' => 'Sincronizzazione Todoist'],
        'toggl' => ['import' => 'Import Toggl'],
        'updates' => ['check' => 'Verifica aggiornamenti'],
    ],
    'hint' => [
        'time' => 'Solo per piani giornalieri/settimanali/mensili.',
        'day' => 'Giorno della settimana 0–6 (0 = domenica) o giorno del mese 1–31.',
        'expression' => 'Solo per gestori: minuto ora giorno mese giorno-settimana.',
        'allowlist' => 'Durata prevista ca. :runtime min. Il job viene eseguito con protezione da sovrapposizioni; intervalli troppo stretti vengono rifiutati lato server.',
    ],
    'flash' => [
        'rescheduled' => 'Il job :job è stato ripianificato.',
        'paused' => 'Il job :job è stato sospeso.',
        'resumed' => 'Il job :job è stato ripreso.',
        'reset' => 'Il job :job utilizza di nuovo il piano predefinito.',
        'test_run_queued' => 'L\'esecuzione di prova per :job è stata accodata.',
        'test_run_cooldown' => 'Attendere — è possibile una sola esecuzione di prova per job ogni :minutes minuti.',
    ],
];
