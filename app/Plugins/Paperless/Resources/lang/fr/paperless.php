<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : paperless.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reprend en lecture seule les documents d’une instance Paperless-ngx auto-hébergée dans l’entrée de documents cloud : tous les documents ou ceux d’une étiquette, d’un correspondant ou d’un type de document. Les règles de l’entrée les dirigent vers les factures entrantes ou le classement des documents.',
    ],
    'settings' => [
        'base_url' => 'URL de base',
        'base_url_help' => 'Adresse de l’instance Paperless-ngx, p. ex. https://paperless.example.com (sans /api).',
        'api_token' => 'Jeton API',
        'api_token_help' => 'À créer dans Paperless-ngx sous « Mon profil ». Le jeton lit avec les droits de son utilisateur ; WorkDiary ne modifie rien dans Paperless. Le droit de lecture est requis sur les documents, étiquettes, correspondants, types de documents et « UISettings » ; seuls les documents visibles pour l’utilisateur sont repris.',
        'allow_private_network' => 'Autoriser les adresses privées',
        'allow_private_network_help' => 'Uniquement pour une instance de votre propre réseau : autorise une URL de base avec une adresse privée ou interne. Journalisé et effectif seulement si l’exploitant l’autorise.',
    ],
    'action' => [
        'connect' => 'Connecter Paperless-ngx',
    ],
    'connect' => [
        'title' => 'Connecter Paperless-ngx',
        'legend' => 'Connexion',
        'instance' => 'Instance des paramètres du plugin : :instance',
        'name' => 'Nom de la connexion',
        'hint' => 'Choisissez ensuite la source (tous les documents, étiquette, correspondant ou type de document), « / » ou un type de document comme dossier racine, puis créez des règles. Le chemin d’un document est type de document/correspondant/nom de fichier.',
        'submit' => 'Vérifier la connexion',
        'not_configured' => 'Saisissez d’abord l’URL de base et le jeton API dans les paramètres du plugin.',
        'to_settings' => 'Vers les paramètres du plugin',
    ],
    'container' => [
        'all' => 'Tous les documents',
        'kind' => [
            'tag' => 'Étiquette',
            'correspondent' => 'Correspondant',
            'type' => 'Type de document',
        ],
    ],
    'flash' => [
        'not_configured' => 'Paperless-ngx n’est pas configuré : l’URL de base ou le jeton API manque dans les paramètres du plugin.',
        'instance_changed' => 'Les paramètres du plugin désignent une autre instance Paperless que cette connexion. Déconnectez-la d’abord ; les documents déjà repris sont conservés.',
    ],
    'health' => [
        'not_configured' => 'Paperless-ngx n’est pas configuré (URL de base ou jeton manquant).',
        'not_connected' => 'Aucune connexion Paperless dans l’entrée de documents cloud pour l’instant.',
        'attention' => 'La connexion Paperless signale une erreur — voir l’entrée de documents cloud.',
    ],
];
