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
