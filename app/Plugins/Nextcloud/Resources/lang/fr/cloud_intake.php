<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cloud_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'action' => [
        'connect_nextcloud' => 'Connecter Nextcloud',
    ],
    'field' => [
        'name' => 'Nom',
    ],
    'nextcloud' => [
        'description' => 'Récupère les documents des dossiers Nextcloud surveillés (WebDAV) — avec règles de dossier, preuve de remise et boîte de réception pour les cas ambigus.',
        'health' => [
            'no_org_context' => 'Aucun contexte d’organisation (exécution système).',
            'attention' => 'Au moins une connexion Nextcloud nécessite une attention (ré-authentification/bloquée).',
            'backup_attention' => 'La cible de sauvegarde Nextcloud demande attention (reconnexion/bloquée) — concerne toutes les organisations.',
            'ok' => 'Connexions Nextcloud en ordre.',
            'error' => 'Échec du contrôle de santé (:class).',
        ],
        'connect_title' => 'Connecter Nextcloud',
        'connect_legend' => 'Identifiants',
        'connect_submit' => 'Connecter',
        'field' => [
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
