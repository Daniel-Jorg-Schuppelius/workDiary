<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup_targets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'nextcloud' => [
        'connect_title' => 'Connecter Nextcloud',
        'connect_legend' => 'Identifiants',
        'connect_submit' => 'Connecter',
        'field' => [
            'name' => 'Nom',
            'server_url' => 'URL du serveur',
            'server_url_help' => 'HTTPS uniquement. Exemple : https://cloud.example.com',
            'username' => 'Nom d’utilisateur',
            'app_password' => 'Mot de passe d’application',
            'app_password_help' => 'Un mot de passe d’application révocable (Paramètres › Sécurité), jamais le mot de passe du compte.',
        ],
        'validation' => [
            'https_required' => 'L’URL du serveur doit commencer par https://.',
            'unsafe_url' => 'L’URL du serveur doit être accessible publiquement (pas de cible interne/privée).',
        ],
    ],
];
