<?php
/*
 * Created on   : Sun Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'status' => 'Backup e ripristino',
        'log_restore_test' => 'Registrare un test di ripristino',
    ],

    'subtitle' => 'Stato dei backup esterni per sorgente, avvisi di freschezza e registro dei test di ripristino eseguiti.',

    'section' => [
        'last_per_source' => 'Ultimo backup per sorgente',
        'restore_register' => 'Registro dei test di ripristino',
        'restore_test' => 'Test di ripristino',
        'retention' => 'Conservazione',
    ],

    'field' => [
        'source' => 'Sorgente',
        'occurred_at' => 'Data e ora',
        'age' => 'Età',
        'size' => 'Dimensione',
        'manifest_hash' => 'Hash del manifesto',
        'state' => 'Stato',
        'tested_on' => 'Testato il',
        'result' => 'Esito',
        'scope' => 'Ambito',
        'restored_size' => 'Ripristinato',
        'restored_size_bytes' => 'Dimensione ripristinata (byte)',
        'duration' => 'Durata',
        'duration_minutes' => 'Durata (minuti)',
        'next_due' => 'Prossima scadenza',
        'performed_by' => 'Eseguito da',
        'notes' => 'Nota',
        'last_passed' => 'Ultimo test riuscito',
        'no_passed_test' => 'Nessun test di ripristino riuscito registrato',
    ],

    'badge' => [
        'fresh' => 'aggiornato',
        'overdue' => 'in ritardo',
    ],

    'value' => [
        'hours' => ':n h',
        'minutes' => ':n min',
        'days_ago' => ':n giorni fa',
    ],

    'action' => [
        'log_restore_test' => 'Registrare un test di ripristino',
        'save' => 'Salva',
        'open_help' => 'Apri il manuale dei backup',
    ],

    'warn' => [
        'no_heartbeat_title' => 'Nessun backup registrato',
        'no_heartbeat_body' => "Non è ancora stato ricevuto alcun heartbeat di backup. Verificare che lo script di backup esterno sia in esecuzione e richiami l'endpoint heartbeat con un token valido.",
        'overdue_title' => 'Backup in ritardo',
        'overdue_body' => 'Almeno una sorgente non segnala un heartbeat da più di :hours ore. Controllare l\'ultimo backup.',
        'restore_overdue_title' => 'Test di ripristino in ritardo',
        'restore_overdue_body' => 'Da più di :days giorni non viene registrato alcun test di ripristino riuscito. Eseguire un test di ripristino e registrarlo qui.',
    ],

    'hint' => [
        'freshness' => "Una sorgente è considerata in ritardo se il suo heartbeat più recente ha più di :hours ore (configurabile tramite BACKUP_HEARTBEAT_FRESHNESS_HOURS).",
        'register_manual' => "Questo è un registro tracciabile. Il ripristino effettivo viene eseguito manualmente o tramite script al di fuori di WorkDiary — l'esecuzione automatizzata del ripristino non fa volutamente parte di questa pagina.",
        'retention' => 'Conservazione consigliata: 7 giornalieri, 4 settimanali, 12 mensili (regola 3-2-1). Almeno un backup offsite in un\'altra ubicazione.',
        'see_docs' => 'I dettagli sulla strategia, sull\'heartbeat e sul ripristino passo passo sono in docs/backup-restore.md.',
    ],

    'empty' => [
        'no_heartbeat' => 'Nessun backup registrato',
        'no_heartbeat_hint' => "Non appena lo script di backup esterno invia un heartbeat, qui comparirà l'ultimo backup per sorgente.",
        'no_restore_tests' => 'Nessun test di ripristino registrato',
    ],

    'placeholder' => [
        'source' => 'es. nightly, offsite, weekly-full',
        'scope' => 'es. DB+storage, solo allegati',
        'notes' => 'Osservazioni, riserve, scostamenti …',
    ],

    'flash' => [
        'restore_test_logged' => 'Test di ripristino registrato.',
    ],

    'generated_at' => 'Aggiornato al: :at',
];
