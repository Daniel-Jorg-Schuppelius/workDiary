<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : intake_upload.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Upload-Kanal der Kundeneingänge (MVP-1078): Einstellungen und Health.
 */

return [
    'settings' => [
        'server_url' => 'Canal de dépôt : URL du serveur',
        'server_url_help' => 'Adresse Nextcloud (https) pour les liens de dépôt des demandes clients — séparée de la réception de documents et de la sauvegarde.',
        'username' => 'Canal de dépôt : utilisateur',
        'app_password' => 'Canal de dépôt : mot de passe d\'application',
        'app_password_help' => 'Mot de passe d\'application révocable de Nextcloud (Paramètres → Sécurité), jamais le mot de passe du compte.',
        'base_folder' => 'Canal de dépôt : dossier de base',
        'link_days' => 'Canal de dépôt : validité des liens (jours)',
        'link_days_help' => 'De 1 à 90 jours ; ensuite, le lien est révoqué après la dernière reprise.',
    ],
    'health' => [
        'attention' => 'Au moins un lien de dépôt des demandes clients n\'a pas pu être récupéré.',
    ],
];
