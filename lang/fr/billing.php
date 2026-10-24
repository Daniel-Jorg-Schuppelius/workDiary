<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'feed' => [
        'title' => 'Flux de documents',
        'subtitle' => 'Devis, factures, pièces et notes de frais sur :range — modifiable via le filtre de dates de l\'en-tête.',
        'empty' => 'Aucun document sur la période choisie',
        'search_placeholder' => 'Numéro, client, fournisseur …',
        'days_short' => 'j',
        'dunning_level' => 'Niveau de relance :level',
        'action' => [
            'dun' => 'Relancer',
            'open_original' => 'Ouvrir l’original',
        ],
        'tab' => [
            'all' => 'Tous',
            'quotes' => 'Devis',
            'outgoing' => 'Factures de vente',
            'incoming' => 'Factures d\'achat',
            'credits' => 'Avoirs',
            'expenses' => 'Notes de frais',
            'other' => 'Autres',
        ],
        'kpi' => [
            'revenue' => 'Produits',
            'expense' => 'Charges (externes)',
            'balance' => 'Solde',
            'internal_mine' => 'Mes notes de frais',
            'internal_all' => 'Notes de frais (toutes)',
            'internal_pending' => 'dont en validation : :amount',
            'open' => 'En cours',
            'overdue' => 'dont en retard',
            'open_count' => '{0} aucune pièce ouverte|{1} :count pièce ouverte|[2,*] :count pièces ouvertes',
            'overdue_count' => ':count sur :total pièces',
            'neutral' => 'Sans effet monétaire',
            'neutral_hint' => 'Les devis, confirmations de commande et bons de livraison sont seulement comptés.',
        ],
        'filter' => [
            'direction' => 'Sens',
            'project' => 'Projet',
            'origin' => 'Origine',
            'only_overdue' => 'En retard uniquement',
            'only_unlinked' => 'Sans pièce comptable',
            'with_archived' => 'Inclure les archivés',
        ],
        'state' => [
            'draft' => 'Brouillon',
            'open' => 'En cours',
            'paid' => 'Clôturé',
            'cancelled' => 'Annulé',
            'deleted' => 'Supprimé dans la source',
        ],
        'foreign_amount' => 'Montant dans une seconde devise',
        'scope' => [
            'mine' => 'Les miennes',
            'all' => 'Toutes',
        ],
        'column' => [
            'kind' => 'Type',
            'origin' => 'Origine',
            'due' => 'Échéance',
            'open' => 'Restant',
        ],
    ],
    'relations' => [
        'title' => 'Documents liés (:count)',
        'column' => [
            'document' => 'Document',
            'number' => 'Numéro',
            'date' => 'Date',
            'state' => 'Statut',
            'system' => 'Système',
            'relation' => 'Lien',
        ],
        'via' => 'via :document :number',
        'schedule' => 'Plan de facturation',
        'quote_version' => 'Devis (version :version)',
        'manufacturing_order' => 'Ordre de fabrication',
        'transfer' => 'Transmission (:channel)',
        'draft' => 'Brouillon',
    ],
    // Bon de livraison comme document propre (phase 145, MVP-1163).
    'delivery_note' => [
        'create' => 'Créer un bon de livraison',
        'edit' => 'Modifier les quantités',
        'edit_hint' => 'Pour une livraison partielle, ajustez la quantité ; 0 retire la position de ce bon de livraison.',
        'details' => 'Livraison',
        'deliver' => 'Livrer',
        'cancel' => 'Annuler',
        'cancel_confirm' => 'Annuler le bon de livraison ? Il reste dans le logiciel de comptabilité.',
        'delivered_on' => 'Livré le :date',
        'to_order' => 'Ordre de fabrication :number',
        'number_pending' => ':program attribue le numéro à la livraison ; d\'ici là, le bon de livraison porte un numéro provisoire.',
        'flash' => [
            'updated' => 'Bon de livraison enregistré.',
            'delivered' => 'Bon de livraison :number livré.',
            'cancelled' => 'Bon de livraison annulé.',
            'issue_failed' => 'Livré, mais le bon de livraison :number reste un brouillon : :error',
        ],
        'error' => [
            'not_confirmed' => 'Un bon de livraison ne se crée qu\'à partir d\'une confirmation de commande confirmée.',
            'fully_delivered' => 'Toutes les positions de la confirmation de commande ont déjà été livrées.',
            'not_editable' => 'Seuls les brouillons sans sortie de stock peuvent être modifiés.',
            'not_draft' => 'Seuls les brouillons peuvent être livrés.',
            'empty' => 'Le bon de livraison n\'a pas de positions.',
            'not_cancellable' => 'Ce bon de livraison ne peut pas être annulé. Corrigez une sortie de stock via le mouvement de stock.',
            'not_delivered' => 'Le bon de livraison n\'a pas encore été livré.',
        ],
    ],
];
