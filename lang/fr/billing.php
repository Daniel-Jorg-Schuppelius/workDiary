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
            'dun_confirm' => 'Créer une relance dans la comptabilité ?',
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
            'overdue' => 'En retard',
            'overdue_count' => '{0} aucun document|{1} :count document|[2,*] :count documents',
            'neutral' => 'Sans effet monétaire',
            'neutral_hint' => 'Les devis, confirmations de commande et bons de livraison sont seulement comptés.',
        ],
        'filter' => [
            'direction' => 'Sens',
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
        ],
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
];
