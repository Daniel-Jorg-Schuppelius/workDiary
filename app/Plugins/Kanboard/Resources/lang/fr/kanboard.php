<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kanboard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les tâches de projets Kanboard sélectionnés comme tâches dans des projets WorkDiary : titre, description, ouverte ou fermée et échéance. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Kanboard',
    'health' => [
        'not_configured' => 'Kanboard n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion Kanboard n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance Kanboard, p. ex. https://kanban.example.com (sans /jsonrpc.php).',
        'username' => 'Nom d’utilisateur',
        'username_help' => 'Laisser vide pour l’accès application (utilisateur « jsonrpc » avec le jeton de Paramètres → API). Avec un nom d’utilisateur, le jeton d’accès personnel de cet utilisateur s’applique, et seulement ses projets.',
        'api_token' => 'Jeton API',
        'api_token_help' => 'Jeton d’application de Paramètres → API ou jeton d’accès personnel de l’utilisateur.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
