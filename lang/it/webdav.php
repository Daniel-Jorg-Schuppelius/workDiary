<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webdav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Archivio WebDAV',
    'intro' => 'I documenti approvati vengono copiati per tipo di documento in un archivio WebDAV esterno (Nextcloud/ownCloud) — con prova di trasferimento (hash, ora, destinazione). WorkDiary resta l\'autorità; le modifiche esterne ai file copiati appaiono come conflitto, mai una sovrascrittura silenziosa.',

    'conflict' => [
        'subtitle' => 'Modifica esterna rilevata — copia sospesa (nessuna sovrascrittura).',
        'action' => [
            'overwrite' => 'Sovrascrivi remoto',
            'import' => 'Importa come nuova versione',
            'detach' => 'Scollega copia',
        ],
        'confirm' => [
            'overwrite' => 'Sovrascrivere il file esterno con lo stato locale? La modifica esterna andrà persa.',
            'import' => 'Importare lo stato esterno come nuova versione locale?',
            'detach' => 'Scollegare definitivamente la copia di questo documento? La connessione resta attiva.',
        ],
        'flash' => [
            'overwritten' => 'File esterno sovrascritto con lo stato locale.',
            'imported' => 'Stato esterno importato come nuova versione locale.',
            'detached' => 'Copia scollegata per questo documento.',
            'failed' => 'Risoluzione del conflitto non riuscita: :reason',
        ],
        'import_note' => 'Importato da WebDAV (risoluzione conflitto).',
    ],

    'health' => [
        'ok' => 'Connesso',
        'failing' => 'Irraggiungibile',
        'inactive' => 'Inattivo',
    ],

    'action' => [
        'mirror' => 'Copia ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'connection' => [
        'heading' => 'Archivio',
    ],

    'field' => [
        'name' => 'Etichetta',
        'base_url' => 'URL della collection',
        'base_url_help' => 'Cartella WebDAV completa, ad es. .../remote.php/dav/files/UTENTE/WorkDiary.',
        'username' => 'Nome utente',
        'app_password' => 'Password app',
        'password_keep' => '•••••••• (lascia invariato)',
        'password_help' => 'Nextcloud: Impostazioni → Sicurezza → Password app. Memorizzata cifrata.',
        'default_folder' => 'Cartella predefinita',
        'active' => 'Attivo',
        'sources' => 'Contenuti replicati',
        'source_document' => 'Documenti (DMS)',
        'source_invoice_pdf' => 'Fatture (PDF)',
        'source_protocol_pdf' => 'Verbali (PDF)',
        'sources_help' => 'Quali contenuti vengono replicati in questo archivio. Senza selezione: solo documenti pubblicati.',
    ],

    'folder' => [
        'heading' => 'Tipo di documento → cartella',
        'help' => 'Associa i tipi di documento a una sottocartella (relativa all\'URL della collection). Senza corrispondenza si applica la cartella predefinita.',
        'type_placeholder' => '— tipo di documento —',
        'path_placeholder' => 'Sottocartella',
    ],

    'flash' => [
        'saved' => 'Archivio WebDAV salvato.',
        'mirror_done' => 'Copia avviata.',
        'disconnected' => 'Archivio WebDAV disconnesso. I file già copiati restano all\'esterno.',
        'no_connection' => 'Nessun archivio WebDAV attivo.',
        'invalid_url' => 'L\'URL della collection deve iniziare con http:// o https://.',
        'password_required' => 'Un nuovo archivio richiede una password app.',
    ],
];
