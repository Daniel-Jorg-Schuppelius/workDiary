<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : google_calendar.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Google Agenda',
    'intro' => 'Les rendez-vous WorkDiary sont publiés via l\'API Google Calendar dans un agenda du compte Google connecté. WorkDiary reste maître ; les rendez-vous annulés y disparaissent et les exécutions répétées ne créent jamais de doublons. Les rendez-vous externes ne sont jamais lus.',
    'plugin_description' => 'Publie les rendez-vous de manière idempotente dans un agenda Google (Calendar API v3, OAuth2) — publication seule, agenda cible sélectionnable.',
    'not_configured_hint' => 'GOOGLE_CALENDAR_CLIENT_ID/SECRET ne sont pas définis — la connexion nécessite d\'abord un client OAuth dans la Google Cloud Console (les scopes agenda sont « sensitive » : vérification de marque ou type de consentement « Internal » pour Workspace).',

    'health' => [
        'badge_ok' => 'Connecté',
        'badge_failing' => 'Injoignable',
        'badge_inactive' => 'Inactif',
        'not_configured' => 'Google Agenda n\'est pas configuré (GOOGLE_CALENDAR_CLIENT_ID/SECRET manquants).',
        'no_org_context' => 'Configuré (aucune organisation dans le contexte).',
        'no_connection' => 'Aucune connexion Google Agenda établie.',
        'inactive' => 'La connexion Google Agenda est déconnectée ou désactivée.',
        'ok' => 'Connecté — liste des agendas disponible.',
        'failing' => 'API Google Calendar injoignable ou accès refusé.',
        'error' => 'Erreur Google Calendar (:class).',
    ],

    'action' => [
        'connect' => 'Connecter à Google',
        'publish' => 'Publier maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'calendar' => [
        'heading' => 'Agenda cible',
        'help' => 'Dans quel agenda du compte connecté la publication a lieu. Sans sélection, l\'agenda principal est utilisé.',
        'target' => 'Agenda',
        'default' => 'Agenda principal',
        // MVP-610a: Rückimport ist Opt-in — er ändert Daten.
        'two_way' => 'Bidirectionnel : importer les modifications externes comme propositions',
        'two_way_hint' => 'Réimport de la liste des modifications de l’agenda cible — nouveaux rendez-vous externes, modifications externes de rendez-vous publiés et suppressions arrivent comme cas dans la boîte d’intégration (jamais de création aveugle).',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Rendez-vous supprimé dans Google Agenda',
    ],

    'flash' => [
        'not_configured' => 'Google Agenda n\'est pas configuré (GOOGLE_CALENDAR_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'Le flux OAuth a expiré ou est invalide. Veuillez recommencer.',
        'oauth_denied' => 'La connexion a été refusée ou annulée.',
        'oauth_failed' => 'L\'échange de jetons a échoué (:class).',
        'connected' => 'Compte Google connecté.',
        'disconnected' => 'Connexion Google Agenda déconnectée. Les rendez-vous déjà publiés restent côté externe.',
        'no_connection' => 'Aucune connexion Google Agenda active.',
        'two_way_saved' => 'Paramètre de réimport enregistré.',
        'calendar_saved' => 'Agenda cible enregistré.',
        'calendar_invalid' => 'L\'agenda sélectionné est introuvable.',
        'publish_done' => 'Publication lancée.',
    ],
];
