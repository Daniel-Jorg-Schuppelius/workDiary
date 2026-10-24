<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plane.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les éléments de travail de projets Plane sélectionnés comme tâches dans des projets WorkDiary : titre, description, statut selon le groupe d’état et date cible. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Espace de travail',
    'task_link' => 'Ouvrir dans Plane',
    'health' => [
        'not_configured' => 'Plane n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion Plane n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’API : https://api.plane.so pour Plane Cloud, sinon l’adresse de votre propre instance (sans /api/v1).',
        'workspace_slug' => 'Identifiant de l’espace de travail',
        'workspace_slug_help' => 'La partie de l’adresse après l’hôte, p. ex. « acme » dans https://app.plane.so/acme/.',
        'api_key' => 'Clé API',
        'api_key_help' => 'À créer dans Plane sous Paramètres du profil → Jetons d’accès personnels ; elle voit les projets dont le profil est membre. Nécessite Plane 0.28 ou plus récent.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
