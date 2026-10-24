<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : paperless.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura i documenti di un’istanza Paperless-ngx self-hosted nell’ingresso documenti cloud: tutti i documenti oppure quelli di un tag, di un corrispondente o di un tipo di documento. Le regole dell’ingresso li indirizzano alle fatture in entrata o all’archivio documenti.',
    ],
    'settings' => [
        'base_url' => 'URL di base',
        'base_url_help' => 'Indirizzo dell’istanza Paperless-ngx, ad es. https://paperless.example.com (senza /api).',
        'api_token' => 'Token API',
        'api_token_help' => 'Lo crei in Paperless-ngx in «Il mio profilo». Il token legge con i permessi del suo utente; WorkDiary non modifica nulla in Paperless. Serve il permesso di lettura su documenti, tag, corrispondenti, tipi di documento e «UISettings»; sono visibili solo i documenti che l’utente può vedere.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un URL di base con indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
    'action' => [
        'connect' => 'Collegare Paperless-ngx',
    ],
    'connect' => [
        'title' => 'Collegare Paperless-ngx',
        'legend' => 'Connessione',
        'instance' => 'Istanza dalle impostazioni del plugin: :instance',
        'name' => 'Nome della connessione',
        'hint' => 'Poi scelga l’origine (tutti i documenti, tag, corrispondente o tipo di documento), «/» o un tipo di documento come cartella radice, e crei le regole. Il percorso di un documento è tipo di documento/corrispondente/nome file.',
        'submit' => 'Verificare la connessione',
        'not_configured' => 'Inserisca prima l’URL di base e il token API nelle impostazioni del plugin.',
        'to_settings' => 'Alle impostazioni del plugin',
    ],
    'container' => [
        'all' => 'Tutti i documenti',
        'kind' => [
            'tag' => 'Tag',
            'correspondent' => 'Corrispondente',
            'type' => 'Tipo di documento',
        ],
    ],
    'flash' => [
        'not_configured' => 'Paperless-ngx non è configurato: nelle impostazioni del plugin manca l’URL di base o il token API.',
        'instance_changed' => 'Le impostazioni del plugin puntano a un’istanza Paperless diversa da questa connessione. La scolleghi prima; i documenti già acquisiti restano.',
    ],
    'health' => [
        'not_configured' => 'Paperless-ngx non è configurato (manca l’URL di base o il token).',
        'not_connected' => 'Ancora nessuna connessione Paperless nell’ingresso documenti cloud.',
        'attention' => 'La connessione Paperless segnala un errore: vedere l’ingresso documenti cloud.',
    ],
];
