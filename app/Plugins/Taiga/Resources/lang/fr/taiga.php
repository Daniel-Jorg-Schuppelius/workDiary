<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taiga.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les récits utilisateur de projets Taiga sélectionnés comme tâches dans des projets WorkDiary : titre, description, ouvert ou fermé et échéance. Les conflits et cas à clarifier vont dans la boîte de réception d’intégration.',
    ],
    'source' => 'Instance',
    'task_link' => 'Ouvrir dans Taiga',
    'health' => [
        'not_configured' => 'Taiga n’est pas configuré (adresse ou accès manquant).',
        'not_connected' => 'La connexion Taiga n’a pas encore été vérifiée.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’API : https://api.taiga.io pour Taiga Cloud, sinon l’adresse de votre propre instance (sans /api/v1).',
        'username' => 'Nom d’utilisateur',
        'username_help' => 'Nom d’utilisateur ou adresse e-mail d’un compte ayant accès en lecture aux projets ; idéalement un compte dédié à l’intégration. Le compte doit avoir, dans les projets, le droit de voir les récits utilisateur.',
        'password' => 'Mot de passe',
        'password_help' => 'Taiga ne délivre pas de jetons API permanents ; l’intégration se reconnecte à chaque exécution. Les comptes connectés via GitHub ou GitLab ont besoin de leur propre mot de passe.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
];
