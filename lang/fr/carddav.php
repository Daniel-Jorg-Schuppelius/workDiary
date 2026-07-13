<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carddav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CardDAV',
    'intro' => 'Les contacts sont lus depuis un carnet d\'adresses CardDAV auto-hébergé (Nextcloud/Radicale/Baïkal) et injectés dans la boîte de réception d\'intégration comme propositions de rapprochement — pas de fusion automatique, aucune écriture sur les données clients. Les cartes inchangées sont ignorées (UID+ETag).',
    'description' => 'Lit les contacts d\'un carnet d\'adresses CardDAV (RFC 6352) et les injecte dans la boîte de réception d\'intégration comme propositions de rapprochement — lecture seule, on-premise, sans compte Microsoft/Google.',

    'health' => [
        'ok' => 'Connecté',
        'failing' => 'Injoignable',
        'inactive' => 'Inactif',
        'no_connection' => 'Aucune connexion CardDAV configurée.',
        'inactive_or_incomplete' => 'La connexion CardDAV est désactivée ou incomplète.',
        'unreachable' => 'Serveur CardDAV injoignable ou identifiants invalides.',
        'error' => 'Erreur CardDAV (:class).',
        'last_error' => 'Dernière erreur : :error',
    ],

    'action' => [
        'discover' => 'Rechercher les carnets d\'adresses',
        'choose_addressbook' => 'Utiliser ce carnet d\'adresses',
        'sync' => 'Synchroniser maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'connection' => [
        'heading' => 'Connexion',
    ],

    'addressbook' => [
        'heading' => 'Carnet d\'adresses',
        'current' => 'Source de synchronisation actuelle : :name',
        'hint' => 'Utilisez « Rechercher les carnets d\'adresses » pour interroger le serveur, puis choisissez un carnet comme source de synchronisation.',
    ],

    'status' => [
        'last_synced' => 'Dernière synchronisation :at.',
    ],

    'field' => [
        'name' => 'Désignation',
        'base_url' => 'URL de base DAV',
        'base_url_help' => 'Nextcloud : .../remote.php/dav — Radicale/Baïkal : racine du serveur. La découverte suit la RFC 6764 (.well-known/carddav).',
        'username' => 'Nom d\'utilisateur',
        'app_password' => 'Mot de passe d\'application',
        'password_keep' => '•••••••• (laisser inchangé)',
        'password_help' => 'Avec la 2FA activée (p. ex. Nextcloud), un mot de passe d\'application est obligatoire. Stocké chiffré.',
        'allow_private_network' => 'Autoriser les adresses privées/internes',
        'allow_private_network_help' => 'À activer uniquement si le serveur CardDAV se trouve sur votre propre réseau (p. ex. 192.168.x.x). Cette action est auditée.',
        'active' => 'Actif',
    ],

    'flash' => [
        'saved' => 'Connexion CardDAV enregistrée.',
        'invalid_url' => 'L\'URL de base doit commencer par http:// ou https://.',
        'private_url_blocked' => 'L\'URL de base pointe vers une adresse privée/interne. Activez l\'autorisation des adresses privées pour un serveur sur votre propre réseau.',
        'password_required' => 'Un mot de passe d\'application est requis pour une nouvelle connexion.',
        'no_connection' => 'Aucune connexion CardDAV active disponible.',
        'discovery_failed' => 'Échec de la recherche des carnets d\'adresses — serveur injoignable ou identifiants invalides.',
        'no_addressbooks' => 'Aucun carnet d\'adresses trouvé sur le serveur.',
        'discovered' => ':count carnets d\'adresses trouvés — veuillez choisir une source de synchronisation.',
        'addressbook_not_discovered' => 'Veuillez d\'abord lancer « Rechercher les carnets d\'adresses » et choisir un carnet découvert.',
        'addressbook_saved' => 'Carnet d\'adresses défini comme source de synchronisation.',
        'not_syncable' => 'Synchronisation impossible — connexion inactive, en erreur ou aucun carnet d\'adresses choisi.',
        'sync_done' => 'Synchronisation lancée. Les nouveaux contacts apparaîtront comme propositions dans la boîte de rapprochement.',
        'disconnected' => 'Connexion CardDAV déconnectée. Les propositions déjà injectées sont conservées.',
    ],
];
