<?php
/*
 * Created on   : Thu Aug 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_contacts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Envoi de contacts (Feature 102, tranche D) : section du panneau Msgraph + bouton de la fiche client.
return [
    'heading' => 'Envoyer les contacts vers Outlook',
    'intro' => 'Envoie les clients WorkDiary comme contacts Outlook du compte connecté à la demande (idempotent — aucun doublon en cas de renvoi).',
    'badge_connected' => 'Connecté',
    'badge_inactive' => 'Déconnecté',
    'account' => 'Compte connecté',
    'connect' => 'Connecter l’envoi de contacts',
    'disconnect' => 'Déconnecter l’envoi de contacts',
    'push_button' => 'Vers Outlook',
    'flash' => [
        'not_configured' => 'Microsoft 365 n’est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'Le processus de connexion a expiré ou est invalide — veuillez recommencer.',
        'oauth_denied' => 'L’autorisation a été annulée.',
        'oauth_failed' => 'La connexion a échoué (:class).',
        'connected' => 'Envoi de contacts vers Outlook connecté.',
        'disconnected' => 'Envoi de contacts déconnecté — jetons d’accès supprimés.',
        'no_connection' => 'Aucune connexion de contacts Microsoft 365 établie.',
        'plugin_disabled' => 'Le plugin Microsoft 365 n’est pas activé.',
        'pushed' => 'Client envoyé comme contact Outlook (ID :id).',
        'push_failed' => 'Envoi échoué (:class).',
    ],
];
