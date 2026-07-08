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
    'title' => 'Team-Messenger',
    'intro' => 'Microsoft Teams und Mattermost/Rocket.Chat erhalten dieselben Ereignisse wie die übrigen Benachrichtigungskanäle. Welche Ereignisse an die Kanäle gehen, steuern Sie in der Benachrichtigungsmatrix (Häkchen „Teams"/„Mattermost" je Ereignis). Die Kanal-URL wird verschlüsselt gespeichert.',
    'to_matrix' => 'Zur Benachrichtigungsmatrix',
    'open' => 'Öffnen',

    'channels_heading' => 'Kanäle',
    'no_channels' => 'Noch kein Kanal verbunden.',
    'add_heading' => 'Kanal hinzufügen',

    'kind' => [
        'teams' => 'Microsoft Teams',
        'mattermost' => 'Mattermost / Rocket.Chat',
    ],

    'field' => [
        'name' => 'Bezeichnung',
        'kind' => 'Kanaltyp',
        'webhook_url' => 'Webhook-URL',
        'webhook_url_help' => 'Incoming-Webhook-URL aus Teams (Connector/Workflow) bzw. Mattermost/Rocket.Chat. Enthält das Geheimnis — wird verschlüsselt gespeichert.',
    ],

    'action' => [
        'disconnect' => 'Trennen',
        'save' => 'Speichern',
        'test' => 'Testen',
    ],

    'col' => [
        'status' => 'Status',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'auto_disabled' => 'Automatisch deaktiviert',
    ],

    'flash' => [
        'saved' => 'Kanal gespeichert.',
        'disconnected' => 'Kanal getrennt.',
        'invalid_url' => 'Die Webhook-URL muss mit https:// beginnen.',
        'test_sent' => 'Testnachricht gesendet.',
        'test_failed' => 'Testnachricht fehlgeschlagen – Kanal nicht erreichbar.',
        'test_inactive' => 'Kanal ist deaktiviert.',
    ],
    'test' => [
        'event' => 'Test',
        'title' => 'WorkDiary-Testnachricht',
        'message' => 'Dieser Kanal ist korrekt verbunden. ✅',
    ],
];
