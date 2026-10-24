<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : glpi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura i ticket di entità GLPI selezionate come attività nei progetti WorkDiary: titolo, descrizione, stato e tempo di risoluzione. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in GLPI',
    'health' => [
        'not_configured' => 'GLPI non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione GLPI non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza GLPI, ad es. https://glpi.example.com (senza /apirest.php). L’API REST deve essere attiva in GLPI in Configurazione → Generale → API.',
        'app_token' => 'Token applicazione',
        'app_token_help' => 'Necessario solo se il client API in GLPI richiede un token applicazione.',
        'user_token' => 'Token utente',
        'user_token_help' => 'Lo generi in GLPI nelle preferenze dell’utente in «Chiavi di accesso remoto» (token API). Sono visibili le entità e i ticket di quell’utente.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
