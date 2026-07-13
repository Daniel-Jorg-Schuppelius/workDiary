<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Calendrier Microsoft 365',
    'intro' => 'Les rendez-vous WorkDiary sont publiés via Microsoft Graph dans un calendrier du compte Microsoft 365 connecté. WorkDiary reste maître ; les rendez-vous annulés y disparaissent et les exécutions répétées ne créent jamais de doublons. Les rendez-vous externes ne sont jamais lus.',
    'plugin_description' => 'Publie les rendez-vous de manière idempotente dans un calendrier Microsoft 365 (Microsoft Graph, OAuth2) — publication seule, calendrier cible sélectionnable.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (et MSGRAPH_TENANT si nécessaire) ne sont pas définis — la connexion nécessite d\'abord un enregistrement d\'application dans le tenant Microsoft.',

    'health' => [
        'badge_ok' => 'Connecté',
        'badge_failing' => 'Injoignable',
        'badge_inactive' => 'Inactif',
        'not_configured' => 'Microsoft 365 n\'est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'no_org_context' => 'Configuré (aucune organisation dans le contexte).',
        'no_connection' => 'Aucune connexion Microsoft 365 établie.',
        'inactive' => 'La connexion Microsoft 365 est déconnectée ou désactivée.',
        'ok' => 'Connecté — liste des calendriers disponible.',
        'failing' => 'Microsoft Graph injoignable ou accès refusé.',
        'error' => 'Erreur Microsoft Graph (:class).',
    ],

    'action' => [
        'connect' => 'Connecter à Microsoft 365',
        'publish' => 'Publier maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'calendar' => [
        'heading' => 'Calendrier cible',
        'help' => 'Dans quel calendrier du compte connecté la publication a lieu. Sans sélection, le calendrier par défaut est utilisé.',
        'target' => 'Calendrier',
        'default' => 'Calendrier par défaut',
    ],

    'flash' => [
        'not_configured' => 'Microsoft 365 n\'est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'Le flux OAuth a expiré ou est invalide. Veuillez recommencer.',
        'oauth_denied' => 'La connexion a été refusée ou annulée.',
        'oauth_failed' => 'L\'échange de jetons a échoué (:class).',
        'connected' => 'Compte Microsoft 365 connecté.',
        'disconnected' => 'Connexion Microsoft 365 déconnectée. Les rendez-vous déjà publiés restent côté externe.',
        'no_connection' => 'Aucune connexion Microsoft 365 active.',
        'calendar_saved' => 'Calendrier cible enregistré.',
        'calendar_invalid' => 'Le calendrier sélectionné est introuvable.',
        'publish_done' => 'Publication lancée.',
    ],
];
