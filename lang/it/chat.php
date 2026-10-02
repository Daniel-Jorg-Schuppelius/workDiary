<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : chat.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Messenger di team',
    'intro' => 'Microsoft Teams e Mattermost/Rocket.Chat ricevono gli stessi eventi degli altri canali di notifica. La scelta degli eventi inviati ai canali avviene nella matrice delle notifiche (casella « Teams »/« Mattermost » per evento). L\'URL del canale viene memorizzato cifrato.',
    'to_matrix' => 'Vai alla matrice delle notifiche',
    'open' => 'Apri',

    'channels_heading' => 'Canali',
    'no_channels' => 'Nessun canale collegato finora.',
    'add_heading' => 'Aggiungi canale',

    'kind' => [
        'teams' => 'Microsoft Teams',
        'mattermost' => 'Mattermost / Rocket.Chat',
    ],

    'field' => [
        'name' => 'Etichetta',
        'kind' => 'Tipo di canale',
        'webhook_url' => 'URL webhook',
        'webhook_url_help' => 'URL del webhook in ingresso di Teams (connettore/workflow) o di Mattermost/Rocket.Chat. Contiene il segreto — memorizzato cifrato.',
    ],

    'action' => [
        'disconnect' => 'Disconnetti',
        'save' => 'Salva',
        'test' => 'Prova',
    ],

    'col' => [
        'status' => 'Stato',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
        'auto_disabled' => 'Disattivato automaticamente',
    ],

    'flash' => [
        'saved' => 'Canale salvato.',
        'disconnected' => 'Canale disconnesso.',
        'invalid_url' => 'L\'URL del webhook deve iniziare con https://.',
        'test_sent' => 'Messaggio di test inviato.',
        'test_failed' => 'Messaggio di test non riuscito – canale non raggiungibile.',
        'test_inactive' => 'Il canale è disattivato.',
    ],
    'test' => [
        'event' => 'Test',
        'title' => 'Messaggio di test WorkDiary',
        'message' => 'Questo canale è collegato correttamente. ✅',
    ],
    // Projekt- und Auftragschat (MVP-1061).
    'subject' => [
        'title' => 'Chat',
        'start' => 'Avvia chat',
        'open' => 'Apri chat',
        'hint' => 'Un canale per tutte le persone assegnate — foto e file appaiono qui.',
        'no_files' => 'Ancora nessun file nella chat.',
        'files' => 'File dalla chat',
        'project' => 'Progetto: :name',
        'diary' => 'Ordine: :name',
        'other' => 'Argomento',
        'description' => 'Canale del progetto o dell’ordine — i membri sono le persone assegnate.',
    ],
];
