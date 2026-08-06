<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Cloud-Dokumenteingang (Feature 080).
return [
    'validation' => [
        'pattern_empty' => 'Il modello di percorso non può essere vuoto.',
        'pattern_triple_star' => 'Modello non valido: «***» non è consentito (solo * e **).',
        'unknown_variable' => 'Variabile di percorso sconosciuta :variable.',
        'duplicate_variable' => 'La variabile di percorso :variable compare più volte.',
    ],
    'title' => [
        'index' => 'Ingresso documenti cloud',
        'subtitle' => 'Leggere i documenti dalle cartelle cloud monitorate e instradarli verso fatture in entrata e DMS tramite regole di cartella.',
        'empty' => 'Nessuna connessione cloud presente.',
    ],
    'field' => [
        'provider' => 'Provider',
        'name' => 'Nome',
        'account' => 'Account',
        'root_folder' => 'Cartella radice',
        'routes' => 'Regole',
        'status' => 'Stato',
        'account_unconfirmed' => 'Account non ancora confermato',
        'container' => 'Container/drive',
        'root_folder_id' => 'ID cartella radice (opzionale)',
    ],
    'picker' => [
        'search_label' => 'Cerca contenitori',
        'search_placeholder' => 'vuoto = drive propri; un termine di ricerca trova anche le raccolte SharePoint',
        'load' => 'Carica contenitori',
        'load_failed' => 'Impossibile caricare i contenitori — inserire l’ID manualmente.',
    ],
    'action' => [
        'connect_dropbox' => 'Collega Dropbox',
        'connect_microsoft' => 'Collega Microsoft 365',
        'connect_google' => 'Collega Google Drive',
        'connect_nextcloud' => 'Collega Nextcloud',
        'preview' => 'Anteprima',
        'save_folder' => 'Applica cartella',
        'disconnect' => 'Scollega',
        'disconnect_confirm' => 'Scollegare davvero? Documenti importati e prove di trasferimento restano; vengono rimossi solo accesso e checkpoint.',
    ],
    'flash' => [
        'not_configured' => 'Il provider non è configurato (chiavi app mancanti nell\'installazione).',
        'state_invalid' => 'La procedura di accesso è scaduta o non valida — riprovare.',
        'oauth_denied' => 'L\'autorizzazione è stata annullata.',
        'oauth_failed' => 'Accesso non riuscito (:class).',
        'account_failed' => 'Impossibile confermare l\'account (:class).',
        'connected' => 'Connessione stabilita — account confermato.',
        'folder_selected' => 'Cartella radice applicata — la prossima esecuzione riparte con una sincronizzazione fresca.',
        'overlapping_root' => 'La cartella radice si sovrappone alla connessione «:name» dello stesso account.',
        'preview_failed' => 'Anteprima non riuscita (:class).',
        'preview_result' => 'Anteprima (prima pagina:more): :files file, :size — :matched con regola, :unmatched senza assegnazione.',
        'disconnected' => 'Connessione rimossa — prove e documenti importati restano.',
        'route_saved' => 'Regola di cartella salvata.',
        'route_deleted' => 'Regola di cartella eliminata.',
    ],
    'dropbox' => [
        'description' => 'Legge i documenti dalle cartelle Dropbox monitorate (ingresso documenti cloud) — con regole di cartella, prova di trasferimento e inbox per i casi dubbi.',
        'health' => [
            'not_configured' => 'Chiavi app Dropbox non configurate.',
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Dropbox richiede attenzione (riautenticazione/bloccata).',
            'ok' => 'Connessioni Dropbox in ordine.',
            'error' => 'Controllo stato non riuscito (:class).',
        ],
    ],
    'google' => [
        'description' => 'Legge i documenti dalle cartelle Google Drive monitorate (ingresso documenti cloud) — Il mio Drive e Drive condivisi; rollout bloccato fino alla verifica OAuth di Google.',
        'health' => [
            'not_configured' => 'Chiavi client Google Drive non configurate.',
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Google Drive richiede attenzione (riautenticazione/bloccata).',
            'ok' => 'Connessioni Google Drive in ordine.',
            'error' => 'Controllo stato non riuscito (:class).',
        ],
    ],
    'nextcloud' => [
        'description' => 'Acquisisce documenti dalle cartelle Nextcloud monitorate (WebDAV) — con regole di cartella, prova di consegna e posta in arrivo per i casi ambigui.',
        'health' => [
            'no_org_context' => 'Nessun contesto organizzazione (esecuzione di sistema).',
            'attention' => 'Almeno una connessione Nextcloud richiede attenzione (ri-autenticazione/bloccata).',
            'ok' => 'Connessioni Nextcloud in ordine.',
            'error' => 'Controllo di integrità non riuscito (:class).',
        ],
        'connect_title' => 'Collega Nextcloud',
        'connect_legend' => 'Credenziali',
        'connect_submit' => 'Collega',
        'field' => [
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
    'route' => [
        'heading' => 'Regole di cartella',
        'create' => 'Crea regola',
        'edit' => 'Modifica regola',
        'save' => 'Salva',
        'delete' => 'Elimina',
        'delete_confirm' => 'Eliminare davvero questa regola?',
        'basics' => 'Regola',
        'pattern' => 'Modello di percorso',
        'pattern_help' => '* = un segmento, ** = qualsiasi profondità; variabili: {customer_number}, {project_number}, {order_number}, {asset_number}, {contract_number}. I casi dubbi vanno nella inbox di integrazione.',
        'target' => 'Destinazione',
        'document_type' => 'Tipo di documento',
        'priority' => 'Priorità',
        'extensions' => 'Estensioni consentite',
        'extensions_help' => 'Separate da virgola; vuoto = tutte (tranne blocchi globali).',
        'max_size' => 'Dimensione max (byte)',
        'auto_version' => 'Adottare automaticamente le nuove revisioni come versioni',
        'auto_version_help' => 'Senza approvazione le nuove revisioni diventano proposte di versione nella inbox.',
        'active' => 'Attiva',
        'inactive' => 'Inattiva',
        'empty' => 'Nessuna regola — senza regola valida la connessione non importa.',
    ],
    'log' => [
        'heading' => 'Registro import',
        'empty' => 'Nessun trasferimento.',
        'path' => 'Percorso di origine',
        'revision' => 'Revisione',
        'reason' => 'Motivo',
        'when' => 'Quando',
    ],
];
