<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Stock',

    'mode' => [
        'local' => 'Local (WorkDiary gère le stock)',
        'external' => 'Externe (le système de gestion gère)',
        'read_only' => 'Lecture seule (géré en externe)',
    ],

    'state' => [
        'physical' => 'Physique',
        'reserved' => 'Réservé',
        'blocked' => 'Bloqué',
        'quality' => 'Contrôle qualité',
        'damaged' => 'Endommagé',
        'scrap' => 'Rebut',
    ],

    'ownership' => [
        'own' => 'Stock propre',
        'customer' => 'Matériel client',
        'consignment' => 'Consignation',
        'supplier' => 'Matériel fournisseur',
        'project' => 'Affecté au projet',
    ],

    'movement' => [
        'receipt' => 'Réception de marchandises',
        'issue' => 'Sortie',
        'return' => 'Retour',
        'transfer_out' => 'Transfert (sortie)',
        'transfer_in' => 'Transfert (entrée)',
        'reserve' => 'Réservation',
        'release_reservation' => 'Réservation libérée',
        'scrap' => 'Rebut',
        'correction' => 'Correction/écart d’inventaire',
        'finished_good_receipt' => 'Entrée de produit fini',
    ],

    'warehouses' => 'Entrepôts',
    'stock' => 'Stock',
    'subtitle' => [
        'warehouses' => 'Gérer les entrepôts du locataire.',
        'stock' => 'Disponibilité et mouvements par entrepôt.',
    ],
    'action' => [
        'create_warehouse' => 'Créer un entrepôt',
        'edit_warehouse' => 'Modifier l’entrepôt',
        'book' => 'Enregistrer un mouvement',
    ],
    'field' => [
        'code' => 'Code',
        'default' => 'Par défaut',
        'available' => 'Disponible',
        'physical' => 'Physique',
        'reserved' => 'Réservé',
        'location_note' => 'Note de localisation',
        'warehouse' => 'Entrepôt',
        'variant' => 'Variante',
        'quantity' => 'Quantité',
        'movement' => 'Mouvement',
        'ownership' => 'Type de propriété',
        'allow_negative' => 'Autoriser le stock négatif',
    ],
    'empty' => [
        'warehouses' => 'Aucun entrepôt créé pour le moment.',
        'stock' => 'Aucun mouvement dans cet entrepôt.',
        'no_selection' => 'Aucun entrepôt sélectionné.',
    ],
    'confirm' => [
        'delete_warehouse' => 'Vraiment supprimer cet entrepôt ? Possible uniquement sans mouvements.',
    ],
    'flash' => [
        'warehouse_created' => 'Entrepôt créé.',
        'warehouse_updated' => 'Entrepôt mis à jour.',
        'warehouse_deleted' => 'Entrepôt supprimé.',
        'warehouse_delete_blocked' => 'Impossible de supprimer l’entrepôt : des mouvements existent.',
        'movement_posted' => 'Mouvement enregistré.',
    ],
    'reservation_status' => [
        'active' => 'Active',
        'fulfilled' => 'Honorée',
        'released' => 'Libérée',
        'cancelled' => 'Annulée',
    ],
    'count_status' => [
        'counting' => 'Comptage',
        'review' => 'Vérification',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ],
    'count_ui' => [
        'title' => 'Inventaire',
        'open' => 'Ouvrir un inventaire',
        'save' => 'Enregistrer le comptage',
        'apply' => 'Comptabiliser les écarts',
        'book' => 'Théorique',
        'counted' => 'Compté',
        'difference' => 'Écart',
        'counted_at' => 'Date de comptage',
        'no_counts' => 'Aucun inventaire pour cet entrepôt.',
        'no_selection' => 'Aucun entrepôt sélectionné.',
        'opened' => 'Inventaire ouvert, stock théorique figé.',
        'saved' => 'Quantités comptées enregistrées.',
        'applied' => 'Écarts comptabilisés comme corrections.',
        'cycle' => 'Cycle (ABC)',
        'cycle_open' => 'Compter le cycle',
        'cycle_empty' => 'Aucun article dû dans cette classe.',
    ],
    'overview' => [
        'avg' => 'Coût moyen',
        'value' => 'Valeur',
        'priority' => 'Priorité',
        'min_stock' => 'Stock minimum',
        'reorder_point' => 'Point de commande',
        'release' => 'Libérer',
        'set_levels' => 'Définir les seuils',
        'reservations' => 'Réservations',
        'below_reorder' => 'Besoins d’approvisionnement',
        'shortfall' => 'Manquant',
        'no_reservations' => 'Aucune réservation active.',
        'reservation_released' => 'Réservation libérée.',
        'levels_saved' => 'Seuils min./de commande enregistrés.',
    ],

    'serial' => [
        'title' => 'Numéros de série',
        'subtitle' => 'Cycle de vie unitaire, preuve d’expédition et vérification d’authenticité.',
        'empty' => 'Aucun numéro de série.',
        'blocked_default' => 'Bloqué',
        'status' => [
            'created' => 'Créé',
            'in_stock' => 'En stock',
            'reserved' => 'Réservé',
            'shipped' => 'Expédié',
            'returned' => 'Repris',
            'blocked' => 'Bloqué',
            'scrapped' => 'Mis au rebut',
        ],
        'source' => [
            'manufactured' => 'Fabrication propre',
            'purchased' => 'Achat',
        ],
        'field' => [
            'serial_no' => 'Numéro de série',
            'status' => 'Statut',
            'source' => 'Origine',
            'article' => 'Article',
            'variant' => 'Variante',
            'warehouse' => 'Entrepôt',
            'customer' => 'Client',
            'order' => 'Ordre de fabrication',
            'delivery' => 'Livraison',
            'shipped_at' => 'Expédié le',
            'reason' => 'Motif',
        ],
        'action' => [
            'block' => 'Bloquer',
            'unblock' => 'Débloquer',
            'scrap' => 'Mettre au rebut',
            'verify' => 'Passeport appareil',
            'search' => 'Rechercher',
        ],
        'flash' => [
            'blocked' => 'Numéro de série bloqué.',
            'unblocked' => 'Numéro de série débloqué.',
            'scrapped' => 'Numéro de série mis au rebut.',
        ],
        'verify' => [
            'title' => 'Passeport appareil / vérification d’authenticité',
            'subtitle' => 'Saisissez un numéro de série pour vérifier son statut et son origine.',
            'placeholder' => 'Numéro de série …',
            'not_found' => 'Aucun numéro de série trouvé – authenticité non confirmée.',
            'found' => 'Numéro de série trouvé.',
        ],
    ],

    'conflict' => [
        'title' => 'Conflits de stock (externe)',
        'empty' => 'Aucun conflit de stock ouvert.',
        'filter' => ['open' => 'Ouverts', 'all' => 'Tous'],
        'col' => [
            'id' => 'Mouvement',
            'operation' => 'Opération',
            'qty' => 'Quantité',
            'status' => 'Statut',
            'actions' => 'Actions',
        ],
        'status' => [
            'open' => 'Ouvert',
            'resolved_local' => 'Local conservé',
            'resolved_remote' => 'Externe repris',
            'compensated' => 'Compensé',
            'dismissed' => 'Rejeté',
        ],
        'action' => [
            'compensate' => 'Contre-écriture',
            'keep_local' => 'Conserver local',
        ],
        'flash' => [
            'kept_local' => 'Conflit clôturé — stock local conservé.',
            'compensated' => 'Conflit compensé — contre-écriture enregistrée.',
        ],
    ],

    'outbox' => [
        'status' => [
            'pending' => 'En attente',
            'processing' => 'En cours d’envoi',
            'confirmed' => 'Confirmé',
            'failed' => 'Échoué',
            'compensation_required' => 'Compensation requise',
        ],
    ],

    'valuation' => [
        'method' => [
            'moving_average' => 'Coût moyen pondéré',
            'fifo' => 'FIFO',
            'fefo' => 'FEFO (péremption d’abord)',
        ],
    ],

    'scan' => [
        'action' => [
            'receipt' => 'Réception',
            'issue' => 'Sortie',
            'transfer' => 'Transfert',
        ],
        'title' => 'Scanner',
        'subtitle' => 'Scanner un code et enregistrer',
        'code' => 'Code',
        'qty' => 'Quantité',
        'book' => 'Enregistrer',
        'action_label' => 'Action',
        'target' => 'Entrepôt cible',
        'invalid' => 'Saisie invalide.',
        'booked' => 'Mouvement enregistré.',
    ],

    'lot' => [
        'title' => 'Lots',
        'subtitle' => 'Stock par lot, fractionnement et fusion',
        'empty' => 'Aucun lot.',
        'lot_no' => 'Lot',
        'article' => 'Article',
        'best_before' => 'DLUO',
        'on_hand' => 'En stock',
        'split' => 'Fractionner',
        'merge' => 'Fusionner',
        'new_lot_no' => 'Nouveau lot',
        'qty' => 'Quantité',
        'from' => 'De',
        'into' => 'Vers',
        'flash' => [
            'split' => 'Lot fractionné.',
            'merged' => 'Lots fusionnés.',
            'unknown' => 'Lot inconnu.',
        ],
    ],

    'label_template' => [
        'title' => 'Modèles d’étiquette',
        'subtitle' => 'Mise en page, format, QR et champs par modèle',
        'add' => 'Nouveau modèle',
        'empty' => 'Aucun modèle d’étiquette.',
        'name' => 'Nom',
        'paper_size' => 'Format',
        'orientation' => 'Orientation',
        'orientation_landscape' => 'Paysage',
        'orientation_portrait' => 'Portrait',
        'with_qr' => 'Code QR',
        'is_default' => 'Modèle par défaut',
        'default' => 'Par défaut',
        'fields' => 'Champs',
        'delete' => 'Supprimer le modèle',
        'field' => [
            'title' => 'Titre',
            'subtitle' => 'Sous-titre',
            'code' => 'Code',
            'code_type' => 'Type de code',
            'lines' => 'Lignes',
        ],
        'flash' => [
            'saved' => 'Modèle enregistré.',
            'deleted' => 'Modèle supprimé.',
        ],
    ],
    'error' => [
        'batch_required' => 'Article suivi par lot : veuillez indiquer un numéro de lot.',
        'serial_required' => 'Article suivi par numéro de série : veuillez indiquer un numéro de série.',
        'serial_qty_one' => 'Les articles sérialisés se réceptionnent numéro par numéro (quantité 1).',
        'tracked_article_manual_move' => 'Article suivi par lot/série : enregistrer via la réception (commande) ou la gestion des lots/séries — pas en stock anonyme.',
    ],
];
