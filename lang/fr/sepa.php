<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sepa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Ordres de paiement',
    'subtitle' => 'Virements et prélèvements groupés au format SEPA',
    'empty' => 'Aucun ordre de paiement créé pour l’instant.',
    'no_items' => 'Aucune position dans cet ordre.',
    'run_created' => 'Ordre de paiement créé.',
    'run_released' => 'Ordre de paiement validé.',
    'run_cancelled' => 'Ordre de paiement annulé.',
    'item_removed' => 'Position retirée.',
    'item_adjusted' => 'Montant du paiement ajusté.',
    'confirm_release' => 'Valider l’ordre de paiement avec :count positions ?',
    'confirm_cancel' => 'Annuler l’ordre de paiement ? Les factures redeviennent payables.',
    'released_by' => 'Validé par',
    'file_hash' => 'Empreinte du fichier (SHA-256)',
    'execution_hint' => 'Date proposée ; la banque exécute au plus tôt ce jour-là.',
    'discount_used' => 'Escompte :percent %',
    'adjust_hint' => 'Montant facturé : :gross. Un paiement inférieur exige un motif.',
    'reference' => 'Facture :number',
    'reference_unknown' => 'Facture sans numéro',
    'document_description' => 'Fichier SEPA de l’ordre de paiement :id',

    'proposal' => [
        'title' => 'Proposition de paiement',
        'subtitle' => 'Factures fournisseurs validées avec la date d’exécution la plus avantageuse',
        'empty' => 'Aucune facture ouverte validée pour paiement.',
    ],

    'action' => [
        'confirm_iban' => 'Confirmer l’IBAN',
        'proposal' => 'Proposition de paiement',
        'create_run' => 'Créer l’ordre de paiement',
        'show' => 'Afficher',
        'release' => 'Valider',
        'export' => 'Fichier SEPA',
        'cancel' => 'Annuler',
        'adjust' => 'Ajuster le montant',
        'remove_item' => 'Retirer la position',
    ],

    'column' => [
        'label' => 'Libellé',
        'kind' => 'Type',
        'account' => 'Compte bancaire',
        'execution_date' => 'Exécution',
        'positions' => 'Positions',
        'total' => 'Total',
        'status' => 'Statut',
        'creditor' => 'Bénéficiaire',
        'invoice_number' => 'Facture',
        'due_date' => 'Échéance',
        'execute_on' => 'Payer le',
        'gross' => 'Montant facturé',
        'amount' => 'Montant payé',
        'note' => 'Remarque',
        'reference' => 'Motif du virement',
        'deduction' => 'Retenue',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'released' => 'validé',
        'exported' => 'exporté',
        'cancelled' => 'annulé',
    ],

    'iban_confirmed' => 'L’IBAN divergent a été confirmé — la position est désormais payable.',

    'blocked' => [
        'missing_iban' => 'IBAN manquant',
        'zero_amount' => 'Montant nul',
        'iban_differs' => 'IBAN différent des données de base',
    ],

    'error' => [
        'no_iban_deviation' => 'L’IBAN de la facture ne diffère pas (ou plus) des données de base du fournisseur.',
        'no_positions' => 'L’ordre de paiement ne contient aucune position.',
        'not_draft' => 'L’ordre de paiement n’est plus un brouillon.',
        'not_released' => 'L’ordre de paiement n’est pas validé.',
        'four_eyes' => 'Principe des quatre yeux : la personne qui a préparé l’ordre de paiement ne peut pas le valider elle-même.',
        'exported_final' => 'Un ordre de paiement exporté ne s’annule plus.',
        'invalid_amount' => 'Le montant payé doit être supérieur à 0 et ne pas dépasser le montant facturé.',
        'reason_required' => 'Un montant réduit exige un motif.',
        'zero_amount' => 'Le montant doit être supérieur à 0.',
        'account_without_iban' => 'Aucun IBAN n’est enregistré pour le compte bancaire choisi.',
        'missing_creditor_id' => 'Aucun identifiant créancier n’est enregistré (paramètre finance.sepa_creditor_id).',
        'mandate_unusable' => 'Le mandat est révoqué ou inutilisé depuis plus de 36 mois.',
        'item_without_mandate' => 'Une position de prélèvement sans mandat ne peut pas être exportée.',
        'unavailable' => 'L’export SEPA n’est pas activé dans cette installation. Activation via :contact.',
    ],

    'mandate' => [
        'title' => 'Mandats SEPA',
        'subtitle' => 'Mandats de prélèvement des clients',
        'empty' => 'Aucun mandat enregistré pour l’instant.',
        'created' => 'Mandat créé.',
        'revoked' => 'Mandat révoqué.',
        'confirm_revoke' => 'Révoquer le mandat ? Aucun prélèvement ne sera plus autorisé.',
        'not_usable' => 'non prélevable',
        'reference_hint' => 'Unique par créancier ; figure sur le relevé du client.',

        'action' => [
            'create' => 'Enregistrer un mandat',
            'revoke' => 'Révoquer',
        ],

        'column' => [
            'reference' => 'Référence du mandat',
            'customer' => 'Client',
            'kind' => 'Type',
            'signed_on' => 'Signé le',
            'last_collected_on' => 'Dernier prélèvement',
            'status' => 'Statut',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'account_holder' => 'Titulaire du compte',
            'note' => 'Note',
        ],
    ],
];
