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
    'title' => 'Messagerie d\'équipe',
    'intro' => 'Microsoft Teams et Mattermost/Rocket.Chat reçoivent les mêmes événements que les autres canaux de notification. Le choix des événements envoyés aux canaux se fait dans la matrice de notifications (case « Teams »/« Mattermost » par événement). L\'URL du canal est stockée chiffrée.',
    'to_matrix' => 'Vers la matrice de notifications',
    'open' => 'Ouvrir',

    'channels_heading' => 'Canaux',
    'no_channels' => 'Aucun canal connecté pour le moment.',
    'add_heading' => 'Ajouter un canal',

    'kind' => [
        'teams' => 'Microsoft Teams',
        'mattermost' => 'Mattermost / Rocket.Chat',
    ],

    'field' => [
        'name' => 'Libellé',
        'kind' => 'Type de canal',
        'webhook_url' => 'URL de webhook',
        'webhook_url_help' => 'URL du webhook entrant de Teams (connecteur/workflow) ou de Mattermost/Rocket.Chat. Contient le secret — stockée chiffrée.',
    ],

    'action' => [
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
        'test' => 'Tester',
    ],

    'col' => [
        'status' => 'Statut',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
        'auto_disabled' => 'Désactivé automatiquement',
    ],

    'flash' => [
        'saved' => 'Canal enregistré.',
        'disconnected' => 'Canal déconnecté.',
        'invalid_url' => 'L\'URL du webhook doit commencer par https://.',
        'test_sent' => 'Message de test envoyé.',
        'test_failed' => 'Échec du message de test – canal injoignable.',
        'test_inactive' => 'Le canal est désactivé.',
    ],
    'test' => [
        'event' => 'Test',
        'title' => 'Message de test WorkDiary',
        'message' => 'Ce canal est correctement connecté. ✅',
    ],
];
