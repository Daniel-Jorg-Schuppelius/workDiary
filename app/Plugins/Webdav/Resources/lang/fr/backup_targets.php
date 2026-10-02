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
    'webdav' => [
        'connect_title' => 'Connecter la cible WebDAV',
        'connect_legend' => 'Identifiants',
        'connect_submit' => 'Connecter et tester',
        'selftest_hint' => 'À la connexion, un dossier de test est créé, un fichier écrit, relu puis supprimé.',
        'field' => [
            'name' => 'Nom',
            'server_url' => 'URL de la collection',
            'server_url_help' => 'HTTPS uniquement. La collection WebDAV complète, p. ex. https://dav.example.com/remote.php/dav/files/backup/',
            'username' => 'Nom d’utilisateur',
            'password' => 'Mot de passe',
            'password_help' => 'De préférence un jeton d’accès dédié et révocable plutôt que le mot de passe du compte.',
            'base_path' => 'Sous-dossier (facultatif)',
            'base_path_help' => 'Vide = directement dans la collection. Le dossier pseudonyme est créé en dessous.',
        ],
        'validation' => [
            'https_required' => 'L’URL de la collection doit commencer par https://.',
            'unsafe_url' => 'L’URL de la collection doit être accessible publiquement (pas de cible interne/privée).',
        ],
        'flash' => [
            'selftest_failed' => 'Le test de connexion a échoué (:class). La cible n’a pas été activée.',
        ],
    ],
];
