<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_onenote.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// OneNote-Übernahme (Feature 155, MVP-815): Sektion im Msgraph-Admin-Panel + Flow-Flashes.
return [
    'heading' => 'Importer depuis OneNote',
    'intro' => 'Importe un bloc-notes OneNote une fois ou à la demande sous forme de notes ou d’articles de connaissances — en lecture seule (Notes.Read), sans réécriture ni synchronisation continue. Le bloc-notes devient une collection, les sections des sous-collections.',
    'badge_connected' => 'Connecté',
    'badge_disabled' => 'Désactivé',
    'account' => 'Compte connecté',
    'connect' => 'Connecter OneNote',
    'disconnect' => 'Déconnecter OneNote',
    'open_hub' => 'Aller à « Connaissances »',
    'enable_hint' => 'Activez d’abord « Autoriser l’import OneNote » dans les paramètres du plugin — ce n’est qu’ensuite que la connexion demande l’autorisation supplémentaire Notes.Read.',
    'flash' => [
        'not_configured' => 'Microsoft 365 n’est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'La connexion a expiré ou n’est pas valide — veuillez recommencer.',
        'oauth_denied' => 'Le consentement a été annulé.',
        'oauth_failed' => 'La connexion a échoué (:class).',
        'connected' => 'OneNote connecté.',
        'disconnected' => 'OneNote déconnecté — jeton d’accès supprimé.',
        'disabled' => 'L’import OneNote est désactivé dans les paramètres du plugin.',
    ],
];
