<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bank.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'menu' => 'Rapprochement des paiements',
        'index' => 'Relevés bancaires',
        'statement' => 'Relevé bancaire',
        'transactions' => 'Opérations bancaires',
        'suggestions' => 'Suggestions d’affectation',
        'allocations' => 'Affectations confirmées',
        'accounts' => 'Comptes bancaires',
        'account' => 'Compte bancaire',
    ],
    'subtitle' => [
        'index' => 'Importer des relevés bancaires (CAMT.053/MT940), vérifier les opérations et les affecter aux factures ou frais ouverts.',
        'accounts' => 'Comptes bancaires de l’organisation pour le rapprochement automatique des relevés entrants.',
    ],
    'field' => [
        'format' => 'Format',
        'imported_at' => 'Importé le',
        'imported_by' => 'Importé par',
        'account' => 'Compte bancaire',
        'period' => 'Période',
        'opening_balance' => 'Solde d’ouverture',
        'closing_balance' => 'Solde de clôture',
        'balance_check' => 'Chaîne de soldes',
        'tx_count' => 'Opérations',
        'open' => 'Ouvert',
        'matched' => 'Affecté',
        'booking_date' => 'Comptabilisation',
        'valuta_date' => 'Date de valeur',
        'amount' => 'Montant',
        'direction' => 'Sens',
        'currency' => 'Devise',
        'counterparty' => 'Contrepartie',
        'purpose' => 'Motif',
        'reference' => 'Référence',
        'status' => 'Statut',
        'score' => 'Score',
        'kind' => 'Type',
        'note' => 'Note',
        'label' => 'Libellé',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Titulaire du compte',
        'datev_account_no' => 'N° de compte DATEV',
        'is_active' => 'Actif',
    ],
    'reason' => [
        'reference' => 'Numéro de facture',
        'amount' => 'Montant correspond',
        'skonto' => 'Escompte',
        'iban' => 'Correspondance IBAN',
        'date' => 'Proximité de date',
        'foreign_currency' => 'Devise étrangère – vérifier manuellement',
    ],
    'action' => [
        'import' => 'Importer un fichier bancaire',
        'upload' => 'Importer',
        'show' => 'Afficher',
        'download' => 'Télécharger le fichier original',
        'confirm' => 'Confirmer',
        'confirm_selected' => 'Confirmer la sélection',
        'ignore' => 'Mettre de côté',
        'unassignable' => 'Non affectable',
        'unmatch' => 'Annuler l’affectation',
        'manual' => 'Affecter manuellement',
        'new_account' => 'Ajouter un compte bancaire',
        'edit_account' => 'Modifier le compte bancaire',
        'delete_account' => 'Supprimer le compte bancaire',
        'manage_accounts' => 'Gérer les comptes bancaires',
    ],
    'import' => [
        'dialog_title' => 'Importer un fichier bancaire',
        'dialog_hint' => 'CAMT.053 (XML) ou MT940. L’import crée uniquement les opérations dans la zone de contrôle et ne modifie aucun statut de facture ou de frais.',
        'format_hint' => 'Formats pris en charge : CAMT.053, MT940, OFX, QIF, QXF ainsi que PAIN.001/008 (ordres de paiement en tant qu’opérations annoncées). La détection se fait selon le contenu, pas selon l’extension du fichier.',
        'file' => 'Fichier',
        'account_optional' => 'Compte bancaire (facultatif, sinon rapprochement automatique via IBAN)',
        'flash' => [
            'imported' => ':count opérations importées.',
        ],
        'error' => [
            'empty' => 'Le relevé ne contient aucune opération.',
            'empty_file' => 'Le fichier est vide.',
            'duplicate_file' => 'Ce fichier a déjà été importé (doublon).',
            'unavailable' => 'L’import bancaire est un module complémentaire optionnel et payant, non activé dans cette installation. Son activation est possible sur demande à :contact.',
        ],
    ],
    'reconcile' => [
        'flash' => [
            'confirmed' => 'Affectation confirmée.',
            'ignored' => 'Opération mise de côté.',
            'unassignable' => 'Opération marquée comme non affectable.',
            'unmatched' => 'Affectation annulée.',
        ],
        'error' => [
            'no_allocations' => 'Aucune affectation n’a été indiquée.',
            'target_not_found' => 'La cible d’affectation est introuvable.',
        ],
    ],
    // Sammelbuchungs-Auflösung je TransactionDetail (Toolkit-Folgepaket 2).
    'split' => [
        'title' => 'Décomposer l’écriture groupée',
        'return_title' => 'Rejet de prélèvement groupé — traiter par transaction individuelle',
        'target' => 'Poste',
        'target_placeholder' => '— Choisir un poste —',
        'no_match' => 'Aucun poste trouvé',
    ],
    // Lastschrift-Rückläufer-Workflow (MVP-334).
    'return' => [
        'badge' => 'Rejet',
        'title' => 'Traiter le rejet de prélèvement',
        'action' => 'Compenser',
        'reason_placeholder' => 'Motif (p. ex. AC04)',
        'flash' => [
            'processed' => 'Rejet traité — affectation d’origine compensée, poste rouvert.',
        ],
        'error' => [
            'same_transaction' => 'L’affectation appartient à l’opération de rejet elle-même.',
            'not_compensatable' => 'Cette affectation ne peut pas être compensée.',
            'already_compensated' => 'Cette affectation a déjà été compensée.',
        ],
        'reason' => [
            'amount' => 'Montant correspondant',
            'reference' => 'Référence correspondante',
            'mandate' => 'Référence de mandat',
            'date' => 'Proximité de date',
        ],
    ],
    'account' => [
        'flash' => [
            'created' => 'Compte bancaire créé.',
            'updated' => 'Compte bancaire mis à jour.',
            'deleted' => 'Compte bancaire supprimé.',
        ],
        'error' => [
            'duplicate_iban' => 'Un compte bancaire existe déjà pour cet IBAN.',
        ],
    ],
    'empty' => [
        'statements' => 'Aucun relevé bancaire importé pour le moment.',
        'transactions' => 'Aucune opération dans ce relevé.',
        'suggestions' => 'Aucune suggestion – affecter manuellement ou mettre de côté.',
        'accounts' => 'Aucun compte bancaire créé pour le moment.',
    ],
];
