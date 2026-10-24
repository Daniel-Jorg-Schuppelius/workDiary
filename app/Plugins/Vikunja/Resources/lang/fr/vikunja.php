<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : vikunja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les tâches de projets Vikunja sélectionnés comme tâches dans des projets WorkDiary : titre, description, terminé et échéance. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Vikunja',
    'health' => [
        'not_configured' => 'Vikunja n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion Vikunja n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance Vikunja, p. ex. https://todo.example.com (sans /api/v1).',
        'api_token' => 'Jeton API',
        'api_token_help' => 'À créer dans Vikunja sous Paramètres → Jetons API, avec droit de lecture sur les projets et tâches. Nécessite Vikunja 1.0 ou plus récent ; avec le droit « other → user », la vérification affiche aussi le nom du compte.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
