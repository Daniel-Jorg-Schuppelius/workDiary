<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : freescout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les conversations de boîtes aux lettres FreeScout sélectionnées comme tâches dans des projets WorkDiary : objet, premier message comme description et statut. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans FreeScout',
    'health' => [
        'not_configured' => 'FreeScout n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion FreeScout n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance FreeScout, p. ex. https://support.example.com (sans /api). Le module « API & Webhooks » doit être actif.',
        'api_key' => 'Clé API',
        'api_key_help' => 'À générer dans FreeScout sous Gérer → API & Webhooks. La clé vaut pour toute l’instance.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
