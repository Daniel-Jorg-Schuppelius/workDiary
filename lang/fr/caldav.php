<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : caldav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CalDAV',
    'intro' => 'Les rendez-vous WorkDiary sont publiés dans un calendrier CalDAV externe (Nextcloud/ownCloud) — sur site, sans compte Microsoft ou Google. WorkDiary reste la référence ; les rendez-vous annulés y disparaissent et les exécutions répétées ne créent jamais de doublons.',

    'health' => [
        'ok' => 'Connecté',
        'failing' => 'Injoignable',
        'inactive' => 'Inactif',
    ],

    'action' => [
        'publish' => 'Publier maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'connection' => [
        'heading' => 'Connexion',
    ],

    'field' => [
        'name' => 'Libellé',
        'base_url' => 'URL de base DAV',
        'base_url_help' => 'Nextcloud : .../remote.php/dav (sans le chemin du calendrier).',
        'username' => 'Nom d\'utilisateur',
        'app_password' => 'Mot de passe d\'application',
        'password_keep' => '•••••••• (laisser inchangé)',
        'password_help' => 'Nextcloud : Paramètres → Sécurité → Mot de passe d\'application. Stocké chiffré.',
        'calendar_path' => 'Chemin du calendrier (collection)',
        'calendar_path_help' => 'Relatif à l\'URL de base, p. ex. calendars/team/planning.',
        'active' => 'Actif',
        'scopes' => 'Contenu publié',
        'scope_events' => 'Événements',
        'scope_schedule' => 'Plannings & congés',
        'scopes_help' => 'Quel contenu est publié dans cette collection. Sans sélection : événements uniquement.',
    ],

    'flash' => [
        'saved' => 'Connexion CalDAV enregistrée.',
        'publish_done' => 'Publication lancée.',
        'disconnected' => 'Connexion CalDAV déconnectée. Les rendez-vous déjà publiés sont conservés en externe.',
        'no_connection' => 'Aucune connexion CalDAV active.',
        'invalid_url' => 'L\'URL de base doit commencer par http:// ou https://.',
        'password_required' => 'Une nouvelle connexion nécessite un mot de passe d\'application.',
    ],
];
