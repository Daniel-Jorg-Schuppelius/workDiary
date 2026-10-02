<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : domain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'error' => 'Controllo di stato fallito (:class).',
        'no_org_context' => 'Nessun contesto organizzativo.',
        'ok' => 'Connessioni DomainReselling in ordine.',
        'pilot_open' => 'Connessione attiva, pilota reale ancora in sospeso.',
    ],
    'plugin' => [
        'description' => 'Collegare e gestire in modo controllato i domini di un account DomainReselling con i clienti.',
    ],
    'settings' => [
        'connection_note' => 'Le credenziali (login/password) sono gestite per connessione — non qui.',
        'connection_note_link' => 'Vai alle connessioni DomainReselling',
        'timeout' => 'Timeout API (secondi)',
        'timeout_help' => 'Tempo di attesa massimo per richiesta al provider. Predefinito: 20.',
        'check_budget_per_hour' => 'Budget di verifica per ora',
        'check_budget_per_hour_help' => 'Numero massimo di verifiche di disponibilità per organizzazione e ora — protegge dai punti di penalità del provider. Predefinito: 300.',
        'check_cache_ttl' => 'Cache di verifica (secondi)',
        'check_cache_ttl_help' => 'Durata di memorizzazione di un risultato di disponibilità; i risultati in cache non consumano budget. Predefinito: 300.',
        'list_page_size' => 'Dimensione pagina elenchi',
        'list_page_size_help' => 'Dimensione del batch delle richieste paginate degli elenchi di domini durante la sincronizzazione. Predefinito: 100.',
        'stale_after_hours' => 'Obsoleto dopo (ore)',
        'stale_after_hours_help' => 'Età dei dati oltre la quale una proiezione di dominio viene contrassegnata come obsoleta. Predefinito: 24.',
        'range_error' => 'Inserire un numero intero tra :min e :max.',
    ],
];
