<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gitea.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Importe les tickets de dépôts sélectionnés d’une instance Gitea ou Forgejo auto-hébergée comme tâches dans des projets WorkDiary, en lecture seule : titre, description, ouvert/fermé et échéance. Les conflits et cas à vérifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Gitea/Forgejo',
    'health' => [
        'not_configured' => 'Gitea/Forgejo n’est pas configuré (URL de base ou jeton manquant).',
        'not_connected' => 'Connexion à Gitea/Forgejo pas encore vérifiée.',
    ],
    'settings' => [
        'base_url' => 'URL de base',
        'base_url_help' => 'Adresse de l’instance Gitea ou Forgejo, p. ex. https://git.example.com (sans /api/v1).',
        'api_token' => 'Jeton d’accès',
        'api_token_help' => 'À créer dans Gitea/Forgejo sous Paramètres → Applications, avec accès en lecture aux dépôts et tickets. Les portées read:user, read:repository et read:issue sont nécessaires.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une URL de base avec une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
