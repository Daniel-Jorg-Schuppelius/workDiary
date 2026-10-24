<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leantime.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les tâches de projets Leantime sélectionnés comme tâches dans des projets WorkDiary : titre, description, statut selon le type de statut et échéance ; les jalons sont exclus. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Leantime',
    'health' => [
        'not_configured' => 'Leantime n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion Leantime n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance Leantime, p. ex. https://leantime.example.com (sans /api/jsonrpc).',
        'api_key' => 'Clé API',
        'api_key_help' => 'À créer dans Leantime sous Paramètres de l’entreprise → API et à associer aux projets à reprendre ; la clé n’est affichée qu’une fois. Nécessite Leantime 3.8 ou plus récent.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
