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
    'title' => 'Microsoft 365',
    'calendar_heading' => 'Calendrier',
    'intro' => 'Les rendez-vous WorkDiary sont publiés via Microsoft Graph dans un calendrier du compte Microsoft 365 connecté. WorkDiary reste maître ; les rendez-vous annulés y disparaissent et les exécutions répétées ne créent jamais de doublons. Les rendez-vous externes ne sont jamais lus.',
    'plugin_description' => 'Publie les rendez-vous de manière idempotente dans un calendrier Microsoft 365 (Microsoft Graph, OAuth2) — publication seule, calendrier cible sélectionnable.',
    'not_configured_hint' => 'MSGRAPH_CLIENT_ID/SECRET (et MSGRAPH_TENANT si nécessaire) ne sont pas définis — la connexion nécessite d\'abord un enregistrement d\'application dans le tenant Microsoft.',

    // Présence Teams sur la page de présence (Feature 102, F).
    'presence' => [
        'heading' => 'Équipe (statut Teams)',
        'state' => [
            'Available' => 'Disponible',
            'AvailableIdle' => 'Disponible (inactif)',
            'Busy' => 'Occupé',
            'BusyIdle' => 'Occupé (inactif)',
            'DoNotDisturb' => 'Ne pas déranger',
            'Away' => 'Absent',
            'BeRightBack' => 'De retour bientôt',
            'Offline' => 'Hors ligne',
            'PresenceUnknown' => 'Inconnu',
        ],
    ],
    // Free/busy dans le dialogue d’événement (Feature 102, C2).
    'availability' => [
        'check' => 'Vérifier la disponibilité (Microsoft 365)',
        'hint' => 'Libre/occupé des participants sélectionnés sur le créneau — sans détails des rendez-vous.',
        'missing_input' => 'Veuillez choisir le début, la fin et au moins un participant.',
        'no_connection' => 'Aucune connexion de calendrier Microsoft 365 active.',
        'failed' => 'La requête de disponibilité a échoué.',
        'free' => 'libre',
        'busy' => 'occupé',
        'unknown' => 'inconnu',
    ],
    // Enregistrement d’application par organisation (Feature 102 variante B).
    'settings' => [
        'client_id' => 'ID client (enregistrement d’application propre)',
        'client_id_help' => 'Vide = l’application de l’installation. Une application Entra propre doit enregistrer les mêmes URIs de redirection.',
        'client_secret' => 'Secret client',
        'client_secret_help' => 'Stocké chiffré ; laisser vide pour conserver la valeur enregistrée.',
        'tenant' => 'Tenant (ID d’annuaire)',
        'tenant_help' => 'GUID du tenant Entra ; vide = valeur de l’application d’instance (par défaut « common »).',
        'tenant_invalid' => 'Le tenant doit être un GUID d’annuaire (ou common/organizations/consumers).',
    ],
    'health' => [
        'badge_ok' => 'Connecté',
        'badge_failing' => 'Injoignable',
        'badge_inactive' => 'Inactif',
        'not_configured' => 'Microsoft 365 n\'est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'no_org_context' => 'Configuré (aucune organisation dans le contexte).',
        'no_connection' => 'Aucune connexion Microsoft 365 établie.',
        'inactive' => 'La connexion Microsoft 365 est déconnectée ou désactivée.',
        'side_connections' => 'Des connexions secondaires Microsoft 365 nécessitent une attention (:intake réception de documents, :backup sauvegarde, :mail courriel — réauthentifiez-vous ou vérifiez les scopes).',
        'ok' => 'Connecté — liste des calendriers disponible.',
        'failing' => 'Microsoft Graph injoignable ou accès refusé.',
        'unreachable' => 'Microsoft Graph est momentanément injoignable (erreur réseau/délai d\'attente).',
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
        'teams_meetings' => 'Créer les nouveaux événements comme réunions Teams (lien de participation)',
        'teams_meetings_hint' => 'Ne concerne que les événements nouvellement publiés — Graph ne peut pas remettre un événement existant « hors ligne ».',
        'two_way' => 'Bidirectionnel : importer les changements externes comme propositions',
        'two_way_hint' => 'Import delta du calendrier cible — nouveaux événements externes, modifications externes et suppressions deviennent des cas de la boîte d’intégration (jamais de création aveugle).',
    ],

    // Application Entra & consentement à l'échelle du tenant (admin consent v2).
    'entra' => [
        'heading' => 'Application Entra & consentement à l\'échelle du tenant',
        'intro' => 'Les utilisateurs connectent leurs services Microsoft 365 via la connexion Microsoft (OAuth2, permissions déléguées uniquement). Si une stratégie du tenant Microsoft empêche les utilisateurs de consentir, un administrateur Entra peut accorder ici les permissions une seule fois pour toute l\'organisation.',
        'consent' => 'Accorder pour l\'organisation (admin consent)',
        'consent_hint' => 'Ouvre la connexion Microsoft ; un rôle d\'administrateur Entra dans le tenant cible est requis. Le consentement couvre le calendrier, l\'envoi d\'e-mails, les contacts, les tâches et la réception de documents.',
        'redirects' => 'URI de redirection pour une inscription d\'application propre',
        'redirects_hint' => 'Une application Entra appartenant au client (paramètres du plugin) doit enregistrer exactement ces URI comme redirections de type « Web » :',
        'redirect_calendar' => 'Calendrier',
        'redirect_mail' => 'Envoi d\'e-mails',
        'redirect_contacts' => 'Contacts',
        'redirect_tasks' => 'Tâches (To Do)',
        'redirect_intake' => 'Réception de documents',
        'redirect_adminconsent' => 'Admin consent',
        'redirect_backup' => 'Cible de sauvegarde (application d\'instance uniquement)',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Rendez-vous supprimé dans Microsoft 365',
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
        'admin_consent_granted' => 'Consentement à l\'échelle du tenant accordé — les utilisateurs peuvent désormais se connecter sans demande de consentement individuelle.',
        'admin_consent_failed' => 'Admin consent non accordé (:error).',
    ],
];
