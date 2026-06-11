<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metrics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Metriche operative',
    ],

    'subtitle' => 'Indicatori tecnici e utilizzo aggregato delle funzionalità di questa installazione.',

    'privacy_notice' => 'Tutte le metriche vengono raccolte e salvate esclusivamente in locale. Non avviene alcun invio esterno; l\'utilizzo delle funzionalità viene conteggiato solo come aggregato giornaliero per organizzazione — senza riferimenti personali e senza contenuti aziendali.',

    'section' => [
        'queue' => 'Coda',
        'backups' => 'Heartbeat di backup',
        'plugin_errors' => 'Errori dei plugin (7 giorni)',
        'storage' => 'Archiviazione',
        'active_users' => 'Utenti attivi (30 giorni)',
        'module_counts' => 'Record per modulo principale',
        'feature_usage' => 'Utilizzo delle funzionalità (30 giorni)',
    ],

    'field' => [
        'version' => 'Versione',
        'queue_pending' => 'Job in attesa',
        'queue_failed' => 'Job falliti',
        'attachments' => 'Allegati',
        'document_versions' => 'Versioni dei documenti',
        'feature' => 'Funzionalità',
        'usage_total' => 'Numero',
        'last_used_on' => 'Ultimo utilizzo',
    ],

    'module' => [
        'diary_entries' => 'Incarichi (diario)',
        'protocols' => 'Protocolli',
        'documents' => 'Documenti',
        'form_submissions' => 'Moduli (compilati)',
        'knowledge_articles' => 'Articoli di conoscenza',
        'communication_notes' => 'Note di comunicazione',
    ],

    'empty' => [
        'queue' => 'Nessuna tabella di coda disponibile (driver sync).',
        'backups' => 'Nessun heartbeat di backup ricevuto finora.',
        'plugin_errors' => 'Nessun errore dei plugin negli ultimi 7 giorni.',
        'active_users' => 'Nessun dato di accesso disponibile.',
        'feature_usage' => 'Nessun utilizzo delle funzionalità registrato finora.',
    ],

    'hint' => [
        'storage_db_metadata' => 'Numero e dimensione secondo i metadati del database (nessuna scansione del file system — l\'occupazione del disco è mostrata nella pagina di diagnostica).',
        'active_users' => 'Utenti distinti con un accesso negli ultimi 30 giorni (fonte: registro di audit).',
        'feature_usage_window' => 'Aggregato per organizzazione e giorno negli ultimi 30 giorni. I dati restano in locale.',
    ],

    'generated_at' => 'Generato: :at',
];
