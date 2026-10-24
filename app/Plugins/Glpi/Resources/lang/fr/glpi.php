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
        'description' => 'Reprend en lecture seule les tickets d’entités GLPI sélectionnées comme tâches dans des projets WorkDiary : titre, description, statut et délai de résolution. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans GLPI',
    'health' => [
        'not_configured' => 'GLPI n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion GLPI n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance GLPI, p. ex. https://glpi.example.com (sans /apirest.php). L’API REST doit être activée dans GLPI sous Configuration → Générale → API.',
        'app_token' => 'Jeton d’application',
        'app_token_help' => 'Nécessaire uniquement si le client API dans GLPI exige un jeton d’application.',
        'user_token' => 'Jeton utilisateur',
        'user_token_help' => 'À générer dans GLPI dans les préférences de l’utilisateur sous « Clés d’accès distant » (jeton d’API). Les entités et tickets de cet utilisateur sont visibles.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
