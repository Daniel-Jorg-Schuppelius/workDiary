<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : firefly.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Rispecchia entrate e uscite di Firefly III e le assegna a fatture o fatture in entrata tramite il numero del documento nella causale. In WorkDiary non viene registrato nulla e in Firefly III non viene scritto nulla.',
    ],
    'page' => [
        'subtitle' => 'Movimenti di conto da Firefly III, assegnati tramite i numeri di documento nella causale — sola lettura.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’installazione Firefly III, ad es. https://finanze.example.com (senza /api).',
        'access_token' => 'Token di accesso personale',
        'access_token_help' => 'Crei un token di accesso personale in Firefly III in Opzioni → Profilo → OAuth.',
        'window_days' => 'Periodo (giorni)',
        'window_days_help' => 'Ogni sincronizzazione legge i movimenti di questo periodo fino a oggi. Da 1 a 365 giorni.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’installazione nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
    'connection' => [
        'title' => 'Connessione',
        'connected' => 'Collegato',
        'not_connected' => 'Non collegato',
        'verify' => 'Verificare la connessione',
        'settings' => 'Credenziali',
        'disconnect' => 'Scollegare',
        'confirm_disconnect' => 'Scollegare Firefly III? La sincronizzazione si ferma; i movimenti rispecchiati restano fino alla prossima esecuzione completa.',
        'sync_now' => 'Sincronizzare ora',
        'not_configured' => 'Firefly III non è configurato: inserisca indirizzo e token nelle impostazioni del plugin.',
        'privacy_note' => 'WorkDiary legge entrate e uscite con causale e controparte e non scrive nulla in Firefly III. WorkDiary non ne registra pagamenti.',
        'instance' => 'Istanza',
        'account' => 'Account',
        'window' => 'Periodo',
        'window_value' => 'Movimenti degli ultimi :days giorni',
        'last_sync' => 'Ultima sincronizzazione',
        'incomplete' => 'incompleta',
        'last_error' => 'Ultimo errore',
    ],
    'list' => [
        'search' => 'Ricerca',
        'search_placeholder' => 'Causale, controparte, numero di documento',
        'all_matches' => 'Tutte le assegnazioni',
        'col' => [
            'date' => 'Data',
            'kind' => 'Tipo',
            'counterparty' => 'Controparte',
            'description' => 'Causale',
            'match' => 'Assegnazione',
            'amount' => 'Importo',
        ],
        'empty' => 'Ancora nessun movimento',
        'foreign_amount' => 'Importo in una seconda valuta',
    ],
    'search' => [
        'domain' => 'Movimenti Firefly III',
    ],
    'flash' => [
        'not_configured' => 'Firefly III non è configurato: nelle impostazioni del plugin mancano l’indirizzo o il token.',
        'verify_failed' => 'La connessione a Firefly III non è riuscita (:class).',
        'source_locked' => 'Le impostazioni del plugin puntano a un’altra istanza. Finché sono rispecchiati movimenti della precedente, il cambio non è possibile.',
        'verified' => 'Connessione Firefly III verificata: :source_name.',
        'not_ready' => 'Verifichi prima la connessione.',
        'sync_failed' => 'La sincronizzazione non è riuscita (:class).',
        'synced' => 'Sincronizzazione: :read letti, :created nuovi, :updated aggiornati, :matched assegnati, :removed rimossi.',
        'disconnected' => 'Firefly III scollegato.',
    ],
    'health' => [
        'not_configured' => 'Firefly III non è configurato (mancano indirizzo o token).',
        'not_connected' => 'La connessione Firefly III non è ancora stata verificata.',
    ],
];
