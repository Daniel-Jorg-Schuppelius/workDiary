<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : freescout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Acquisisce in sola lettura le conversazioni di caselle FreeScout selezionate come attività nei progetti WorkDiary: oggetto, primo messaggio come descrizione e stato. Conflitti e casi da chiarire finiscono nella inbox di integrazione.',
    ],
    'source' => 'Istanza',
    'task_link' => 'Aprire in FreeScout',
    'health' => [
        'not_configured' => 'FreeScout non è configurato (mancano l’indirizzo o l’accesso).',
        'not_connected' => 'La connessione FreeScout non è ancora stata verificata.',
    ],
    'settings' => [
        'base_url' => 'Indirizzo',
        'base_url_help' => 'Indirizzo dell’istanza FreeScout, ad es. https://support.example.com (senza /api). Il modulo «API & Webhooks» deve essere attivo.',
        'api_key' => 'Chiave API',
        'api_key_help' => 'La generi in FreeScout in Gestisci → API & Webhooks. La chiave vale per l’intera istanza.',
        'allow_private_network' => 'Consentire indirizzi privati',
        'allow_private_network_help' => 'Solo per un’istanza nella propria rete: consente un indirizzo privato o interno. Viene registrato e ha effetto solo se il gestore lo consente.',
    ],
];
