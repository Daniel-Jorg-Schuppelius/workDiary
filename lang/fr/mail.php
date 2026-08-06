<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Réception d\'e-mails',
    'intro' => 'Les boîtes IMAP connectées sont relevées par le planificateur ; les nouveaux e-mails arrivent comme suggestions dans la boîte d\'intégration et sont associés à un client — jamais créés à l\'aveugle. Les e-mails traités sont seulement marqués/déplacés, jamais supprimés. WorkDiary n\'est pas un client de messagerie.',
    'to_inbox' => 'Vers la boîte d\'affectation',

    'mailboxes_heading' => 'Boîtes aux lettres',
    'no_connections' => 'Aucune boîte connectée pour le moment.',
    'add_heading' => 'Ajouter une boîte',

    'inbox' => [
        'no_subject' => '(sans objet)',
        'book_action' => 'Enregistrer comme note de communication',
        'book_ticket_action' => 'Enregistrer comme ticket de service',
        'book_customer_placeholder' => '… client (vide = expéditeur détecté)',
    ],

    'dms' => [
        'action' => 'Importer dans la gestion documentaire',
        'origin' => 'Importé depuis l’e-mail : :subject (Message-ID :message_id)',
        'imported' => ':count pièce(s) jointe(s) importée(s) dans la gestion documentaire.',
        'none' => 'Aucune pièce jointe importable disponible.',
    ],

    'encryption' => [
        'none' => 'Aucune',
    ],

    'field' => [
        'name' => 'Libellé',
        'transport' => 'Transport',
        'host' => 'Serveur IMAP',
        'port' => 'Port',
        'encryption' => 'Chiffrement',
        'username' => 'Nom d\'utilisateur',
        'password' => 'Mot de passe',
        'folder' => 'Dossier',
        'processed_folder' => 'Dossier cible (traité)',
        'processed_folder_placeholder' => 'facultatif, p. ex. Traité',
        'active' => 'Actif',
    ],

    'action' => [
        'poll' => 'Relever maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'col' => [
        'host' => 'Compte',
        'status' => 'Statut',
        'last_polled' => 'Dernier relevé',
    ],

    'status' => [
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'flash' => [
        'saved' => 'Boîte enregistrée.',
        'disconnected' => 'Boîte déconnectée.',
        'polled' => 'Relève lancée.',
        'booked' => 'E-mail enregistré comme entrée de communication.',
        'book_failed' => 'Enregistrement échoué.',
        'ticket_booked' => 'E-mail enregistré comme ticket de service.',
        'ticket_failed' => 'Création du ticket échouée.',
        'dms_failed' => 'Reprise dans la gestion documentaire échouée.',
        'already_resolved' => 'Cette entrée est déjà résolue.',
        'password_required' => 'Une nouvelle boîte nécessite un mot de passe.',
        'msgraph_connection_required' => 'Une boîte Microsoft 365 nécessite d’abord la connexion d’envoi de mails dans le plugin Microsoft 365 (scope Mail.ReadWrite).',
        'customer_required' => 'Aucun client associé.',
    ],
    'reference' => [
        'customer_number' => 'Numéro de client dans le texte : :number',
        'invoice_number' => 'Numéro de facture dans le texte : :number',
        'project_number' => 'Numéro de projet dans le texte : :number',
    ],
    'transport' => [
        'msgraph' => 'Microsoft 365 (Graph)',
        'msgraph_hint' => 'Microsoft 365 : utilise la connexion mail Graph de l’organisation — pas d’identifiants IMAP nécessaires.',
    ],
];
