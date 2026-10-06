<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ebics.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// EBICS-Bankzugang (MVP-124).
return [
    'title' => 'Accès bancaire EBICS',
    'section' => [
        'access' => 'Données d’accès de la banque',
        'steps' => 'Mise en place',
        'journal' => 'Historique',
    ],
    'field' => [
        'host_url' => 'URL EBICS de la banque',
        'ebics_host' => 'ID hôte',
        'ebics_partner' => 'ID client (ID partenaire)',
        'ebics_user' => 'ID abonné (ID utilisateur)',
    ],
    'hint' => [
        'host_url' => 'Indiquée avec les ID hôte, client et abonné dans la lettre d’accès EBICS de la banque (EBICS 3.0).',
        'active' => 'Le traitement nocturne récupère les relevés ; récupérés jusqu’au :date.',
    ],
    'step' => [
        'keys' => 'Générer les clés (signature, authentification, chiffrement).',
        'initialize' => 'Envoyer les clés publiques à la banque (INI et HIA).',
        'letter' => 'Imprimer la lettre d’initialisation, la signer et l’envoyer à la banque.',
        'activate' => 'Après activation par la banque : récupérer les clés de la banque.',
    ],
    'action' => [
        'save' => 'Enregistrer',
        'keys' => 'Générer les clés',
        'initialize' => 'Envoyer à la banque',
        'letter' => 'Télécharger la lettre',
        'activate' => 'Récupérer les clés de la banque',
        'fetch' => 'Récupérer les relevés maintenant',
        'suspend' => 'Bloquer l’accès',
        'submit' => 'Envoyer par EBICS',
        'confirm_not_submitted' => 'Confirmer comme non transmis',
    ],
    'confirm' => [
        'suspend' => 'Bloquer l’accès auprès de la banque ? Il faudra ensuite de nouvelles clés et une nouvelle lettre.',
        'submit' => 'Envoyer maintenant ce lot de paiements à la banque par EBICS ? Vous l’autorisez ensuite auprès de la banque.',
        'not_submitted' => 'Avez-vous vérifié auprès de la banque que cet ordre n’a pas été reçu ? Le lot de paiement pourra ensuite être transmis à nouveau.',
    ],
    'last_error' => 'Dernière erreur : :error',
    'flash' => [
        'saved' => 'Données d’accès enregistrées.',
        'keys_created' => 'Clés générées.',
        'initialized' => 'Clés envoyées à la banque. Veuillez signer et remettre la lettre d’initialisation.',
        'activated' => 'Clés de la banque récupérées — l’accès est activé.',
        'suspended' => 'Accès bloqué.',
        'fetched' => ':statements relevés importés, :skipped déjà présents.',
        'submitted' => 'Lot de paiements envoyé (ordre :order). Veuillez l’autoriser auprès de la banque.',
        'submission_released' => 'Transmission enregistrée comme non effectuée. Le lot de paiement peut être transmis à nouveau.',
    ],
    'error' => [
        'host_not_allowed' => 'Cette adresse n’est pas autorisée comme accès bancaire.',
        'locked_after_keys' => 'Une fois les clés générées, les données d’accès ne peuvent plus être modifiées — bloquez d’abord l’accès.',
        'invalid_step' => 'Cette étape ne correspond pas à l’état de la mise en place.',
        'not_initialized' => 'La lettre n’est disponible qu’après l’envoi des clés à la banque.',
        'not_active' => 'L’accès EBICS n’est pas activé.',
        'no_keys' => 'Aucune clé n’existe pour cet accès.',
        'no_data' => 'La banque n’a pas de nouvelles données disponibles.',
        'bank_rejected' => 'La banque a refusé l’ordre.',
        'failed' => 'La connexion à la banque a échoué.',
        'already_submitted' => 'Ce lot de paiements a déjà été envoyé par EBICS.',
        'outcome_unclear' => 'Le résultat du dernier envoi est incertain. Veuillez d’abord vérifier auprès de la banque si l’ordre a été reçu.',
    ],
    'letter' => [
        'title' => 'Lettre d’initialisation EBICS (INI/HIA)',
        'sent_at' => 'Envoyée le',
        'key' => [
            'A' => 'Clé bancaire (signature)',
            'X' => 'Clé d’authentification',
            'E' => 'Clé de chiffrement',
        ],
        'hash' => 'Valeur de hachage (SHA-256) :',
        'certificate' => 'Certificat émis le :date',
        'confirmation' => 'Je confirme par la présente que les clés ci-dessus ont été transmises à la banque.',
        'place_date' => 'Lieu, date',
        'signature' => 'Signature de l’abonné',
        'printed_at' => 'Créée le :date',
    ],
    'run' => [
        'submitted' => 'Envoyé par EBICS le :date (ordre :order).',
        'unclear' => 'Transmission EBICS commencée le :date — résultat incertain.',
    ],
];
