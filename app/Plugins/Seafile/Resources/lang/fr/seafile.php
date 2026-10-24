<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : seafile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule des fichiers d’une bibliothèque d’une instance Seafile auto-hébergée dans la réception de documents cloud et sert de cible de sauvegarde chiffrée. Les règles de réception dirigent les fichiers vers les factures entrantes ou le classement des documents.',
    ],
    'settings' => [
        'base_url' => 'Adresse',
        'base_url_help' => 'Adresse de l’instance Seafile pour la réception de documents, p. ex. https://seafile.example.com (sans /api2).',
        'api_token' => 'Jeton API',
        'api_token_help' => 'Jeton de compte d’un utilisateur Seafile ayant accès en lecture à la bibliothèque. Pour la réception, WorkDiary ne modifie rien dans Seafile. Le jeton est renvoyé par POST /api2/auth-token/ avec nom d’utilisateur et mot de passe (avec l’authentification à deux facteurs, également l’en-tête X-Seafile-OTP avec le code actuel).',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
    'action' => [
        'connect_intake' => 'Connecter Seafile',
    ],
    'connect' => [
        'title' => 'Connecter Seafile',
        'legend' => 'Connexion',
        'instance' => 'Instance des paramètres du plugin : :instance',
        'name' => 'Nom de la connexion',
        'hint' => 'Choisissez ensuite la bibliothèque, le dossier racine qu’elle contient, puis créez des règles. Les bibliothèques chiffrées ne sont pas proposées.',
        'submit' => 'Vérifier la connexion',
        'not_configured' => 'Saisissez d’abord l’adresse et le jeton API dans les paramètres du plugin.',
        'to_settings' => 'Aller aux paramètres du plugin',
    ],
    'container' => [
        'library' => 'Bibliothèque',
    ],
    'flash' => [
        'not_configured' => 'Seafile n’est pas configuré : adresse ou jeton API manquant dans les paramètres du plugin.',
        'instance_changed' => 'Les paramètres du plugin pointent vers une autre instance Seafile que cette connexion. Déconnectez-la d’abord ; les justificatifs déjà repris sont conservés.',
    ],
    'health' => [
        'not_configured' => 'Seafile n’est pas configuré (adresse ou jeton manquant).',
        'not_connected' => 'Aucune connexion Seafile dans la réception de documents cloud pour le moment.',
        'attention' => 'La connexion Seafile signale une erreur — voir la réception de documents cloud.',
        'backup_attention' => 'Une cible de sauvegarde Seafile demande votre attention — voir les cibles de sauvegarde.',
    ],
    'backup' => [
        'connect_title' => 'Connecter Seafile comme cible de sauvegarde',
        'connect_legend' => 'Identifiants',
        'connect_submit' => 'Connecter',
        'name' => 'Nom',
        'server_url' => 'URL du serveur',
        'server_url_help' => 'HTTPS uniquement. Exemple : https://seafile.example.com',
        'api_token' => 'Jeton API',
        'api_token_help' => 'Jeton de compte d’un utilisateur Seafile dédié aux sauvegardes, avec accès en écriture à la bibliothèque. Le jeton est renvoyé par POST /api2/auth-token/ avec nom d’utilisateur et mot de passe (avec l’authentification à deux facteurs, également l’en-tête X-Seafile-OTP avec le code actuel).',
        'library' => 'Bibliothèque',
        'library_help' => 'Nom ou ID d’une bibliothèque existante non chiffrée. WorkDiary n’écrit que dans son propre dossier à l’intérieur.',
        'https_required' => 'L’URL du serveur doit commencer par https://.',
        'unsafe_url' => 'L’URL du serveur doit être accessible publiquement (pas de cible interne/privée).',
        'library_not_found' => 'Cette bibliothèque n’existe pas pour ce compte. Créez-la dans Seafile ou vérifiez le nom et le partage.',
        'library_encrypted' => 'Cette bibliothèque est chiffrée. Choisissez une bibliothèque non chiffrée — WorkDiary chiffre lui-même la sauvegarde.',
        'library_ambiguous' => 'Plusieurs bibliothèques portent ce nom. Indiquez plutôt l’ID de la bibliothèque.',
        'library_read_only' => 'Le compte ne peut pas écrire dans cette bibliothèque. Choisissez une bibliothèque avec droit de lecture et d’écriture.',
    ],
];
