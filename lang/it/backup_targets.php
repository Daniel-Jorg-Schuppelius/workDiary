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

    'nextcloud' => [
        'connect_title' => 'Collega Nextcloud',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega',
        'field' => [
            'name' => 'Nome',
            'server_url' => 'URL del server',
            'server_url_help' => 'Solo HTTPS. Esempio: https://cloud.example.com',
            'username' => 'Nome utente',
            'app_password' => 'Password app',
            'app_password_help' => 'Una password app revocabile (Impostazioni › Sicurezza), mai la password normale dell’account.',
        ],
        'validation' => [
            'https_required' => 'L’URL del server deve iniziare con https://.',
            'unsafe_url' => 'L’URL del server deve essere raggiungibile pubblicamente (nessuna destinazione interna/privata).',
        ],
    ],
    // Generisches WebDAV-Backupziel (Feature 123, MVP-612).
    's3' => [
        'connect_title' => 'Collega destinazione di backup S3',
        'connect_legend' => 'Archiviazione a oggetti compatibile S3',
        'connect_submit' => 'Collega e verifica',
        'selftest_hint' => 'Prima del salvataggio viene scritto, riletto ed eliminato un file di prova. Se fallisce, la destinazione non viene attivata.',
        'field' => [
            'name' => 'Denominazione',
            'endpoint' => 'Endpoint (vuoto per AWS S3)',
            'endpoint_help' => 'Inserire l indirizzo HTTPS per MinIO, Wasabi, Hetzner o Scaleway. Se vuoto, AWS S3 viene dedotto dalla regione.',
            'region' => 'Regione',
            'region_help' => 'Spesso arbitraria su archivi self-hosted — us-east-1 è il valore consueto.',
            'bucket' => 'Bucket',
            'access_key' => 'Access key',
            'secret_key' => 'Secret key',
            'secret_key_help' => 'Salvata cifrata e mai più mostrata.',
            'prefix' => 'Prefisso (facoltativo)',
            'prefix_help' => 'Sottocartella nel bucket. WorkDiary vi crea la propria cartella pseudonimo.',
            'path_style' => 'Indirizzare il bucket nel percorso (path style)',
            'path_style_help' => 'Necessario per MinIO e per la maggior parte degli archivi self-hosted. AWS S3 non lo richiede.',
        ],
        'validation' => [
            'https_required' => 'L endpoint deve iniziare con https://.',
            'unsafe_url' => 'Questo endpoint punta a una rete privata. Abilitazione tramite S3_BACKUP_ALLOW_PRIVATE_TARGETS.',
        ],
        'flash' => [
            'selftest_failed' => 'La destinazione non ha superato il test di scrittura/lettura (:class). Non è stata attivata.',
        ],
    ],

    'webdav' => [
        'connect_title' => 'Collega destinazione WebDAV',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega e verifica',
        'selftest_hint' => 'Al collegamento viene creata una cartella di prova, scritto un file, riletto e poi eliminato.',
        'field' => [
            'name' => 'Nome',
            'server_url' => 'URL della collection',
            'server_url_help' => 'Solo HTTPS. La collection WebDAV completa, ad es. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Nome utente',
            'password' => 'Password',
            'password_help' => 'Preferibilmente un token di accesso dedicato e revocabile anziché la password dell’account.',
            'base_path' => 'Sottocartella (facoltativa)',
            'base_path_help' => 'Vuoto = direttamente nella collection. La cartella pseudonimo viene creata al di sotto.',
        ],
        'validation' => [
            'https_required' => 'L’URL della collection deve iniziare con https://.',
            'unsafe_url' => 'L’URL della collection deve essere raggiungibile pubblicamente (nessuna destinazione interna/privata).',
        ],
        'flash' => [
            'selftest_failed' => 'Il test di connessione non è riuscito (:class). La destinazione non è stata attivata.',
        ],
    ],
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
