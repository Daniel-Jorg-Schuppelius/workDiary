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
    'intro' => 'Les boîtes sont relevées régulièrement à partir du dernier point atteint, y compris les e-mails déjà lus. Les nouveaux e-mails arrivent comme suggestions dans la boîte d’intégration, les factures dans les factures entrantes ; les e-mails traités sont seulement marqués ou déplacés, jamais supprimés.',
    'to_inbox' => 'Vers la boîte d\'affectation',
    'title_edit' => 'Modifier la boîte',
    'to_inbox_count' => 'Vers la boîte d’affectation (:count)',

    'mailboxes_heading' => 'Boîtes aux lettres',
    'no_connections' => 'Aucune boîte connectée pour le moment.',
    'add_heading' => 'Ajouter une boîte',

    'inbox' => [
        'invoice_action' => 'Reprendre comme facture entrante',
        'no_subject' => '(sans objet)',
        'invoice_mailbox' => 'Boîte de réception des factures · :from',
        'book_action' => 'Enregistrer comme note de communication',
        'book_ticket_action' => 'Enregistrer comme ticket de service',
        'book_customer_placeholder' => '… client (vide = expéditeur détecté)',
        // Rechnungsnachfrage und Notiz am Auftrag (Feature 166, MVP-1201)
        'inquiry_action' => 'Enregistrer comme demande sur facture',
        'inquiry_number_placeholder' => 'Numéro de facture/document',
        'order_action' => 'Enregistrer comme note sur la commande',
        'order_placeholder' => '… choisir la commande',
        'order_no_customer' => 'Sans client',
        'invoice_review' => [
            'multiple' => 'Plusieurs factures détectées – veuillez affecter',
            'sender_unknown' => 'Facture détectée, expéditeur inconnu – veuillez vérifier',
            'no_customer' => 'Document détecté, aucun client affecté – veuillez vérifier',
            'generic' => 'Facture détectée – veuillez vérifier',
        ],
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
        'mailbox_address' => 'Adresse de la boîte',
        'mailbox_address_hint' => 'Laissez vide pour la boîte du compte connecté. Pour une boîte partagée (p. ex. factures@…), le compte doit y avoir un accès complet dans Exchange, et l’envoi d’e-mails dans le plugin Microsoft 365 doit ensuite être reconnecté pour que l’application obtienne l’autorisation Mail.ReadWrite.Shared.',
        'password_keep' => 'Laissez vide pour conserver le mot de passe enregistré.',
        'fetch_since' => 'Relever à partir du',
        'fetch_since_hint' => 'Les e-mails reçus avant ce jour ne sont pas relevés. Une date antérieure récupère des e-mails plus anciens.',
        'folder_hint_list' => 'Les suggestions proviennent de la boîte ; séparez les sous-dossiers par « / », p. ex. Boîte de réception/Factures.',
        'folder_hint_error' => 'La liste des dossiers n’a pas pu être chargée (:error). Saisissez le dossier manuellement et séparez les sous-dossiers par « / ».',
        'folder_hint_free' => 'Après le premier enregistrement, la boîte de dialogue propose les dossiers de la boîte. Séparez les sous-dossiers par « / », p. ex. INBOX/Factures.',
        'einvoice_intake' => 'Boîte de factures : reprendre les factures des pièces jointes dans les factures entrantes',
        'einvoice_intake_hint' => 'Chaque fichier PDF, XML ou image d’un e-mail devient une facture entrante. Choisissez de préférence un dossier dédié dans lequel une règle classe les factures.',
        'callreport_intake' => 'Boîte des rapports d’appels : reprendre les listes d’appels FRITZ!Box (CSV) dans l’import des listes d’appels',
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
        'create' => 'Nouvelle boîte',
        'edit' => 'Modifier',
        'backfill' => 'Récupérer les anciens e-mails',
        'activate' => 'Activer',
        'poll' => 'Relever maintenant',
        'disconnect' => 'Déconnecter',
        'save' => 'Enregistrer',
    ],

    'col' => [
        'purpose' => 'Utilisation',
        'fetch_since' => 'à partir du :date',
        'host' => 'Compte',
        'status' => 'Statut',
        'last_polled' => 'Dernier relevé',
    ],

    'status' => [
        'error' => 'Erreur',
        'backfill_running' => 'Récupération en cours · :count traités',
        'active' => 'Actif',
        'inactive' => 'Inactif',
    ],

    'flash' => [
        'invoice_imported' => 'Repris dans les factures entrantes : :count.',
        'invoice_duplicate' => 'Cette facture figure déjà dans les factures entrantes.',
        'invoice_none' => 'Aucune pièce jointe n’est un fichier PDF, XML ou image.',
        'invoice_failed' => 'La reprise dans les factures entrantes a échoué.',
        'backfill_started' => 'Récupération lancée. Les prochaines relèves traitent les anciens e-mails par lots.',
        'activated' => 'Boîte activée.',
        'chained_folders' => 'Remarque : un dossier de cette boîte est aussi le dossier de relève ou de traitement de « :name ». Les e-mails seraient transmis d’une entrée à l’autre.',
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
        'customer_required' => 'Aucun client associé.',
        'inquiry_booked' => 'Demande pour :number enregistrée.',
        'inquiry_not_found' => 'Aucune facture ni aucun document pour :number.',
        'inquiry_ambiguous' => 'Plusieurs documents pour :number – veuillez saisir le numéro exact.',
        'inquiry_duplicate' => 'Ce message est déjà enregistré comme demande.',
        'inquiry_failed' => 'La demande n\'a pas pu être enregistrée.',
        'order_booked' => 'E-mail enregistré comme note sur la commande.',
        'order_required' => 'Aucune commande sélectionnée.',
        'order_failed' => 'L\'enregistrement sur la commande a échoué.',
    ],
    'transport' => [
        'imap' => 'Boîte IMAP',
    ],
    'access' => [
        'own_mailbox' => 'Boîte du compte connecté',
    ],
    'group' => [
        'mailbox' => 'Boîte aux lettres',
        'folders' => 'Dossiers et date de début',
        'purpose' => 'Utilisation',
    ],
    'purpose' => [
        'invoices' => 'Factures',
        'callreports' => 'Listes d’appels',
        'inbox' => 'Boîte d’intégration',
    ],
    'validation' => [
        'duplicate_folder' => 'Il existe déjà une entrée avec ce dossier pour cet accès.',
    ],
    'backfill' => [
        'title' => 'Récupérer les anciens e-mails',
        'submit' => 'Récupérer',
        'intro' => 'Les prochaines relèves traitent tous les e-mails du dossier « :folder » à partir du jour choisi, y compris ceux déjà lus.',
        'note_folder' => 'Seul ce dossier est récupéré. Pour une boîte générale, créez une deuxième entrée avec le dossier des factures.',
        'note_inbox' => 'Les e-mails sans facture arrivent comme d’habitude dans la boîte d’intégration.',
        'note_duplicates' => 'Les e-mails et fichiers déjà repris sont reconnus et ne sont pas créés deux fois.',
        'note_graph_limit' => 'Microsoft 365 fournit au plus 5 000 e-mails par passage.',
    ],
    'error' => [
        'folder_missing' => 'Le dossier « :folder » est introuvable dans la boîte aux lettres.',
    ],
    'reference' => [
        'customer_number' => 'Numéro de client dans le texte : :number',
        'invoice_number' => 'Numéro de facture dans le texte : :number',
        'project_number' => 'Numéro de projet dans le texte : :number',
    ],
];
