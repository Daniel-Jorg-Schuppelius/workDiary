<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Destinazioni di backup cloud',
    'description' => 'Copie offsite cifrate dell\'intera installazione (strategia 3-2-1). Il testo in chiaro non lascia mai l\'installazione — vengono caricate solo parti cifrate.',

    'master_key_missing' => 'BACKUP_MASTER_KEY non è impostata — senza la chiave di backup dell\'installazione non è possibile creare né ripristinare backup.',
    'recovery_key_missing' => 'Nessuna chiave di recupero configurata: se BACKUP_MASTER_KEY va persa, tutti i backup cloud sono irrimediabilmente persi. Impostare BACKUP_RECOVERY_PUBLIC_KEY e conservare la chiave privata offline.',

    'connect' => 'Collega',
    'reconnect' => 'Nuovo accesso',
    'disconnect' => 'Scollega',
    'disconnect_confirm' => 'Scollegare davvero? I dati remoti restano intatti; i backup pianificati si fermano.',
    'cleanup' => 'Pulizia',
    'no_connections' => 'Nessuna destinazione di backup collegata.',
    'account' => 'Account',
    'quota' => 'Spazio',
    'quota_value' => ':used di :total utilizzati',
    'quota_unknown' => 'Utilizzo dello spazio sconosciuto',
    'pilot_note' => 'Pilota in sospeso: questo adattatore non è ancora stato testato contro il provider reale.',

    'generations' => [
        'title' => 'Generazioni di backup',
        'empty' => 'Nessuna generazione di backup presente.',
        'snapshot' => 'Snapshot',
        'target' => 'Destinazione',
        'class' => 'Classe',
        'age' => 'Creata',
        'size' => 'Dimensione',
        'status' => 'Stato',
        'verified' => 'Verificata',
        'restore_tested' => 'Test di ripristino',
        'restore_pending' => 'salvata, ripristino non confermato',
        'hold' => 'Conservazione legale',
        'actions' => 'Azioni',
        'hold_set_action' => 'Attiva conservazione',
        'hold_release_action' => 'Rilascia conservazione',
        'delete_action' => 'Elimina',
        'delete_confirm' => 'Eliminare davvero questa generazione? I dati remoti e la registrazione verranno rimossi.',
    ],

    'cleanup_page' => [
        'title' => 'Pulizia — inventario remoto',
        'description' => 'Anteprima degli oggetti nell\'area di backup di questa connessione. L\'eliminazione avviene solo dopo conferma per generazione.',
        'empty' => 'Nessun oggetto remoto trovato nell\'area di backup.',
        'known' => 'Generazione nota',
        'orphan' => 'Orfana (nessuna registrazione nel database)',
        'error' => 'Impossibile caricare l\'inventario remoto: :message',
        'back' => 'Torna alla panoramica',
    ],

    'flash' => [
        'not_configured' => 'Il provider non è configurato (client ID/secret mancanti).',
        'state_invalid' => 'La procedura di accesso è scaduta o non valida — ripetere l\'operazione.',
        'oauth_denied' => 'L\'autorizzazione è stata annullata o negata.',
        'oauth_failed' => 'Scambio di token fallito (:class).',
        'account_failed' => 'Conferma dell\'account fallita (:class).',
        'scope_missing' => 'Autorizzazione richiesta mancante (:scope) — la destinazione è bloccata.',
        'connected' => 'Destinazione di backup collegata e attiva.',
        'disconnected' => 'Connessione rimossa. I dati remoti restano intatti.',
        'hold_set' => 'Conservazione legale attivata — la generazione è protetta dall\'eliminazione.',
        'hold_released' => 'Conservazione legale rilasciata.',
        'hold_blocks_delete' => 'Questa generazione ha una conservazione legale e non può essere eliminata.',
        'cleanup_failed' => 'Pulizia remota fallita (:class).',
        'generation_deleted' => 'Generazione rimossa (remoto e registrazione).',
    ],
];
