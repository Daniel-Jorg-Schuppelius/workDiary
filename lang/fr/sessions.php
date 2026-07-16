<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Utilisateurs connectés',
    ],

    'subtitle' => 'Qui est connecté et où — sessions actives et accès API par utilisateur, avec possibilité de déconnexion à distance.',

    'privacy_notice' => 'Seules les métadonnées sont affichées (IP, appareil, horodatages) — jamais le contenu des sessions ni les valeurs de jetons.',

    'hint' => [
        'driver' => "Les sessions ne sont listables qu'avec le pilote base de données ; pilote actuel : :driver. Sans le pilote database, aucune déconnexion à distance ciblée n'est possible.",
        'terminals' => 'Les terminaux de pointage sont des appareils physiques (pas une connexion utilisateur). « Désactiver » verrouille l\'appareil, sans déconnecter d\'utilisateur.',
        'remote_support' => 'Sessions de maintenance à distance importées — historique uniquement ; non résiliables depuis workDiary.',
    ],

    'stat' => [
        'users' => 'Utilisateurs',
        'online' => 'En ligne',
        'sessions' => 'Sessions',
        'tokens' => 'Jetons API',
    ],

    'badge' => [
        'online' => 'En ligne',
        'this_device' => 'Cet appareil',
    ],

    'section' => [
        'sessions' => 'Sessions web/app',
        'tokens' => 'Jetons API',
        'devices' => 'Appareils de localisation',
        'terminals' => 'Terminaux de pointage',
        'remote_support' => 'Maintenance à distance récente',
    ],

    'col' => [
        'device' => 'Appareil',
        'ip' => 'IP',
        'last_activity' => 'Dernière activité',
        'name' => 'Nom',
        'created' => 'Créé',
        'last_used' => 'Dernière utilisation',
        'action' => 'Action',
        'terminal' => 'Terminal',
        'status' => 'Statut',
        'last_seen' => 'Vu pour la dernière fois',
        'provider' => 'Fournisseur',
        'remote' => 'Identifiant',
        'started' => 'Début',
        'ended' => 'Fin',
    ],

    'terminal' => [
        'inactive' => 'Désactivé',
        'offline' => 'Hors ligne',
    ],

    'last_login' => 'Dernière connexion',

    'live' => [
        'changed' => 'Les connexions actives ont changé.',
        'reload' => 'Recharger la liste',
    ],

    'action' => [
        'revoke_all' => 'Déconnecter tous les appareils',
        'revoke_session' => 'Déconnecter',
        'revoke_token' => 'Révoquer',
        'revoke_device' => 'Déconnecter',
        'deactivate_terminal' => 'Désactiver',
    ],

    'confirm' => [
        'revoke_all' => 'Déconnecter :name de tous les appareils ? Les sessions existantes et « rester connecté » seront invalidées.',
        'revoke_session' => 'Vraiment déconnecter cette session à distance ?',
        'revoke_token' => 'Vraiment révoquer ce jeton API ?',
        'revoke_device' => 'Vraiment déconnecter cet appareil de localisation ?',
        'deactivate_terminal' => 'Vraiment désactiver le terminal « :name » ? L\'appareil ne pourra plus se connecter.',
    ],

    'empty' => [
        'title' => 'Aucune connexion active.',
        'description' => "Personne dans cette organisation n'est actuellement connecté.",
    ],

    'error' => [
        'own_current_session' => 'Votre propre session actuelle ne peut pas être terminée ici — utilisez la déconnexion normale.',
        'session_gone' => "La session n'existe plus.",
        'token_gone' => "Le jeton n'existe plus.",
        'device_gone' => "L'appareil n'existe plus ou est déjà déconnecté.",
    ],

    'flash' => [
        'session_revoked' => 'Session déconnectée à distance.',
        'all_revoked' => ':name a été déconnecté de tous les appareils.',
        'token_revoked' => 'Jeton API révoqué.',
        'device_revoked' => 'Appareil de localisation déconnecté.',
        'terminal_deactivated' => 'Terminal désactivé.',
    ],
];
