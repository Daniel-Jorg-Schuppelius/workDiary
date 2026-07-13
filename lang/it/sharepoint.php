<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sharepoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Archiviazione SharePoint',
    'intro' => 'I documenti approvati vengono replicati per tipo di documento in una raccolta documenti SharePoint tramite Microsoft Graph — con prova di trasferimento (hash, ora, destinazione). WorkDiary resta il riferimento; le modifiche esterne ai file replicati emergono come conflitti, mai adottate in silenzio.',
    'plugin_description' => 'Replica i documenti approvati in una raccolta documenti SharePoint tramite Microsoft Graph — con prova di trasferimento e visualizzazione dei conflitti, senza canale di ritorno.',
    'not_configured_hint' => 'SHAREPOINT_CLIENT_ID/SECRET (o i valori di ripiego MSGRAPH_*) non sono impostati — la connessione può essere stabilita solo dopo la registrazione dell\'app nel tenant Microsoft.',

    'health' => [
        'badge_ok' => 'Connesso',
        'badge_failing' => 'Non raggiungibile',
        'badge_inactive' => 'Inattivo',
        'not_configured' => 'SharePoint non è configurato (SHAREPOINT_/MSGRAPH_CLIENT_ID/SECRET mancanti).',
        'no_org_context' => 'Configurato (nessuna organizzazione nel contesto).',
        'no_connection' => 'Nessuna connessione SharePoint stabilita.',
        'inactive' => 'La connessione SharePoint è disconnessa, in pausa o senza raccolta di destinazione.',
        'ok' => 'Connesso — raccolta di destinazione raggiungibile.',
        'failing' => 'Microsoft Graph non raggiungibile o accesso negato.',
        'error' => 'Errore Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Connetti con Microsoft 365',
        'mirror' => 'Replica ora',
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
    ],

    'target' => [
        'heading' => 'Destinazione: sito + raccolta documenti',
        'help' => 'Cerca prima un sito, poi scegli la raccolta documenti. Entrambi vengono convalidati lato server tramite Microsoft Graph — con Sites.Selected compaiono solo i siti autorizzati.',
        'current' => 'Destinazione attuale',
        'search' => 'Cerca sito',
        'search_placeholder' => 'Nome del sito o parola chiave',
        'search_action' => 'Cerca',
        'no_sites' => 'Nessun sito trovato (verifica il termine di ricerca; con Sites.Selected l\'amministratore del tenant deve autorizzare il sito).',
        'selected' => 'Selezionato',
        'drive' => 'Raccolta documenti',
        'no_drives' => 'Nessuna raccolta documenti trovata in questo sito.',
    ],

    'settings' => [
        'heading' => 'Regole cartelle + origini',
    ],

    'field' => [
        'default_folder' => 'Cartella predefinita',
        'active' => 'Attivo',
        'sources' => 'Contenuti replicati',
        'source_document' => 'Documenti (DMS)',
        'source_invoice_pdf' => 'Fatture (PDF)',
        'source_protocol_pdf' => 'Protocolli (PDF)',
        'sources_help' => 'Quali contenuti vengono replicati in questa raccolta. Senza selezione solo i documenti approvati.',
    ],

    'folder' => [
        'heading' => 'Tipo di documento → cartella',
        'help' => 'Associa i tipi di documento a una sottocartella (relativa alla raccolta). Senza corrispondenza vale la cartella predefinita.',
        'type_placeholder' => '— tipo di documento —',
        'path_placeholder' => 'Sottocartella',
    ],

    'conflict' => [
        'subtitle' => 'Modifica esterna rilevata — replica sospesa (nessuna sovrascrittura).',
        'action' => [
            'overwrite' => 'Sovrascrivi remoto',
            'import' => 'Importa come nuova versione',
            'detach' => 'Scollega la replica',
        ],
        'confirm' => [
            'overwrite' => 'Sovrascrivere il file esterno con lo stato locale? La modifica esterna andrà persa.',
            'import' => 'Adottare lo stato esterno come nuova versione locale?',
            'detach' => 'Scollegare definitivamente la replica di questo documento? La connessione resta attiva.',
        ],
        'flash' => [
            'overwritten' => 'File esterno sovrascritto con lo stato locale.',
            'imported' => 'Stato esterno importato come nuova versione locale.',
            'detached' => 'Replica di questo documento scollegata.',
            'failed' => 'Risoluzione del conflitto non riuscita: :reason',
        ],
        'import_note' => 'Importato da SharePoint (risoluzione del conflitto).',
    ],

    'flash' => [
        'not_configured' => 'SharePoint non è configurato (ID client/secret mancanti).',
        'state_invalid' => 'Il flusso OAuth è scaduto o non è valido — riconnettersi.',
        'oauth_denied' => 'Microsoft non ha restituito un codice di autorizzazione (flusso annullato?).',
        'oauth_failed' => 'Scambio del token non riuscito (:class).',
        'connected' => 'Connesso con Microsoft 365. Ora scegli sito + raccolta.',
        'disconnected' => 'Connessione SharePoint disconnessa. I file già replicati restano all\'esterno.',
        'no_connection' => 'Nessuna connessione SharePoint attiva disponibile.',
        'site_invalid' => 'Il sito scelto non è raggiungibile o non è autorizzato.',
        'drive_invalid' => 'La raccolta documenti scelta non appartiene al sito scelto.',
        'target_saved' => 'Raccolta di destinazione salvata.',
        'saved' => 'Impostazioni SharePoint salvate.',
        'mirror_done' => 'Replica avviata.',
    ],
];
