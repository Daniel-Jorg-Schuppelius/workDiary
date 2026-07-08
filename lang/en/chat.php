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
    'title' => 'Team messenger',
    'intro' => 'Microsoft Teams and Mattermost/Rocket.Chat receive the same events as the other notification channels. Which events go to the channels is controlled in the notification matrix (the "Teams"/"Mattermost" checkbox per event). The channel URL is stored encrypted.',
    'to_matrix' => 'Go to notification matrix',
    'open' => 'Open',

    'channels_heading' => 'Channels',
    'no_channels' => 'No channel connected yet.',
    'add_heading' => 'Add channel',

    'kind' => [
        'teams' => 'Microsoft Teams',
        'mattermost' => 'Mattermost / Rocket.Chat',
    ],

    'field' => [
        'name' => 'Label',
        'kind' => 'Channel type',
        'webhook_url' => 'Webhook URL',
        'webhook_url_help' => 'Incoming webhook URL from Teams (connector/workflow) or Mattermost/Rocket.Chat. Contains the secret — stored encrypted.',
    ],

    'action' => [
        'disconnect' => 'Disconnect',
        'save' => 'Save',
        'test' => 'Test',
    ],

    'col' => [
        'status' => 'Status',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'auto_disabled' => 'Auto-disabled',
    ],

    'flash' => [
        'saved' => 'Channel saved.',
        'disconnected' => 'Channel disconnected.',
        'invalid_url' => 'The webhook URL must start with https://.',
        'test_sent' => 'Test message sent.',
        'test_failed' => 'Test message failed – channel unreachable.',
        'test_inactive' => 'Channel is inactive.',
    ],
    'test' => [
        'event' => 'Test',
        'title' => 'WorkDiary test message',
        'message' => 'This channel is connected correctly. ✅',
    ],
];
