<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : msgraph_mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Envoi de mails via Graph (Feature 102) : section mail du panneau d'administration Msgraph + messages du flux.
return [
    'heading' => 'Envoi d’e-mails via Microsoft 365',
    'intro' => 'Envoie les e-mails de WorkDiary (factures, relances, notifications) via Microsoft Graph au lieu de SMTP — authentification moderne, sans SMTP en authentification basique.',
    'badge_connected' => 'Connecté',
    'badge_inactive' => 'Déconnecté',
    'mailer_hint' => 'Le mailer msgraph n’est pas actif actuellement. Activez-le via MAIL_MAILER=msgraph (ou une chaîne failover incluant msgraph) dans l’installation.',
    'account' => 'Compte connecté',
    'from_address' => 'Adresse d’expéditeur (optionnelle)',
    'from_placeholder' => 'p. ex. facturation@entreprise.fr (boîte partagée)',
    'from_hint' => 'Vide = le compte connecté envoie en son propre nom. Une adresse différente nécessite le droit Exchange « Envoyer en tant que » et le scope Mail.Send.Shared.',
    'save_to_sent' => 'Enregistrer une copie dans le dossier Éléments envoyés',
    'connect' => 'Connecter l’envoi d’e-mails',
    'disconnect' => 'Déconnecter l’envoi d’e-mails',
    'flash' => [
        'not_configured' => 'Microsoft 365 n’est pas configuré (MSGRAPH_CLIENT_ID/SECRET manquants).',
        'state_invalid' => 'Le processus de connexion a expiré ou est invalide — veuillez recommencer.',
        'oauth_denied' => 'L’autorisation a été annulée.',
        'oauth_failed' => 'La connexion a échoué (:class).',
        'connected' => 'Envoi d’e-mails via Microsoft 365 connecté.',
        'disconnected' => 'Envoi d’e-mails déconnecté — jetons d’accès supprimés.',
        'no_connection' => 'Aucune connexion mail Microsoft 365 établie.',
        'settings_saved' => 'Paramètres de messagerie enregistrés.',
        'test_sent' => 'Message de test envoyé à :to (via Microsoft Graph).',
        'test_failed' => 'Échec de l’envoi de test : :error',
        'test_no_recipient' => 'Aucune adresse de destinataire — veuillez saisir une adresse e-mail.',
    ],

    'test' => [
        'subject' => ':app — Test (Microsoft 365)',
        'body' => '<p>Ce message de test a été envoyé par :app via Microsoft Graph.</p>',
        'recipient' => 'Destinataire (facultatif)',
        'recipient_placeholder' => 'Par défaut : compte connecté',
        'hint' => 'Envoie directement via la connexion Graph — indépendamment de MAIL_MAILER.',
        'send' => 'Envoyer un e-mail de test',
    ],
];
