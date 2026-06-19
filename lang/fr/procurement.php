<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Commandes',
    'subtitle' => 'Commandes, réception et suggestions de réapprovisionnement',
    'empty' => 'Aucune commande pour le moment.',

    'action' => [
        'create' => 'Nouvelle commande',
        'add_line' => 'Ajouter une ligne',
        'submit' => 'Commander',
        'receive' => 'Réceptionner',
        'cancel' => 'Annuler',
        'suggestions' => 'Suggestions de commande',
        'apply' => 'Créer les commandes',
        'incoming' => 'Réceptions attendues',
    ],

    'field' => [
        'number' => 'N°',
        'supplier' => 'Fournisseur',
        'warehouse' => 'Entrepôt',
        'ordered_qty' => 'Commandé',
        'received_qty' => 'Reçu',
        'unit_price' => 'Prix unitaire',
        'article' => 'Article',
        'qty' => 'Quantité',
        'expected_at' => 'Date de livraison',
        'note' => 'Note',
    ],

    'flash' => [
        'created' => 'Commande créée.',
        'line_added' => 'Ligne ajoutée.',
        'ordered' => 'Commande passée.',
        'received' => 'Réception enregistrée.',
        'cancelled' => 'Commande annulée.',
        'suggestions_applied' => ':count commande(s) créée(s).',
        'unknown_article' => 'Article inconnu.',
        'unknown_line' => 'Ligne inconnue.',
        'no_warehouse' => 'Aucun entrepôt sélectionné.',
    ],

    'ui' => [
        'suggestions_title' => 'Suggestions de commande',
        'needed' => 'Besoin',
        'suggested' => 'Suggestion',
        'none' => 'Aucune suggestion.',
        'select_warehouse' => 'Choisir un entrepôt',
        'incoming_title' => 'Réceptions attendues',
        'incoming_subtitle' => 'Lignes ouvertes des commandes passées',
        'incoming_none' => 'Aucune réception attendue.',
        'open' => 'Ouvert',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'ordered' => 'Commandé',
        'partially_received' => 'Partiellement reçu',
        'received' => 'Reçu',
        'cancelled' => 'Annulé',
    ],

    'advice_status' => [
        'announced' => 'Annoncé',
        'received' => 'Réceptionné',
        'cancelled' => 'Annulé',
    ],

    'advice' => [
        'title' => 'Avis de livraison',
        'announce' => 'Saisir un avis de livraison',
        'reference' => 'N° avis / bon de livraison',
        'announced_qty' => 'Annoncé',
        'receive' => 'Enregistrer la réception',
        'flash' => [
            'announced' => 'Avis de livraison saisi.',
            'received' => 'Réception enregistrée depuis l’avis.',
            'cancelled' => 'Avis de livraison annulé.',
        ],
    ],
];
