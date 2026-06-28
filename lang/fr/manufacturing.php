<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Fabrication',

    'capacity' => [
        'title' => 'Capacité',
        'subtitle' => 'Postes de charge et charge (avec temps de réglage) sur la période choisie',
        'day' => 'Jour',
        'period_note' => 'Charge sur la période d’en-tête :from – :to (capacité = capacité journalière × jours).',
        'add' => 'Nouveau poste de charge',
        'empty' => 'Aucun poste de charge.',
        'work_center' => 'Poste de charge',
        'code' => 'Code',
        'capacity' => 'Capacité',
        'planned' => 'Planifié',
        'free' => 'Libre',
        'utilization' => 'Taux de charge',
        'setup' => 'Temps de réglage',
        'assign' => 'Affecter un poste',
        'minutes' => 'Minutes',
        'flash' => [
            'created' => 'Poste de charge créé.',
            'assigned' => 'Poste affecté.',
            'assign_failed' => 'Affectation impossible.',
        ],
    ],

    'planning' => [
        'title' => 'Planification de production',
        'subtitle' => 'Besoins matières multi-niveaux (MRP) et indicateurs qualité',
        'explode' => 'Calculer les besoins',
        'requirements' => 'Besoins matières',
        'no_bom' => 'Aucune nomenclature.',
        'level' => 'Niveau',
        'source' => 'Source',
        'make' => 'Fabrication',
        'buy' => 'Achat',
        'gross' => 'Brut',
        'net' => 'Net',
        'quality' => 'Indicateurs qualité',
        'yield' => 'Rendement',
        'scrap_rate' => 'Taux de rebut',
        'rework_rate' => 'Taux de retouche',
        'spc' => 'SPC (étapes de mesure)',
        'measurement' => 'Mesure',
        'out_of_spec' => 'Hors tolérance',
    ],

    'procurement_mode' => [
        'in_house' => 'Fabrication interne',
        'purchase' => 'Achat',
        'subcontract' => 'Sous-traitance',
    ],

    'quantity_kind' => [
        'fixed' => 'Quantité fixe',
        'per_unit' => 'Quantité par unité',
        'ratio' => 'Proportion (recette)',
    ],
    'delivery_note' => [
        'title' => 'Bon de livraison',
        'date' => 'Date de livraison',
        'order' => 'Ordre',
        'recipient' => 'Destinataire',
        'warehouse' => 'Entrepôt',
        'no_customer' => 'Aucun client renseigné',
        'footer_note' => 'Preuve de remise uniquement — pas une facture. Veuillez confirmer la réception.',
        'col' => [
            'sku' => 'Réf. article',
            'name' => 'Désignation',
            'qty' => 'Quantité',
            'unit' => 'Unité',
        ],
    ],
    'parameter_type' => [
        'number' => 'Nombre',
        'measure' => 'Mesure (avec unité)',
        'choice' => 'Choix',
        'text' => 'Texte',
        'date' => 'Date',
        'bool' => 'Oui/Non',
    ],
    'parameter' => [
        'error' => [
            'required' => 'Le paramètre obligatoire « :param » est manquant.',
            'invalid' => 'Le paramètre « :param » a une valeur non valide.',
        ],
    ],

    'status' => [
        'draft' => 'Brouillon',
        'released' => 'Libéré',
        'in_progress' => 'En cours',
        'waiting' => 'En attente',
        'blocked' => 'Bloqué',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé',
    ],

    'facturation_status' => [
        'pending' => 'En attente',
        'handed_over' => 'Transmis',
        'invoiced' => 'Facturé',
        'failed' => 'Échoué',
        'not_required' => 'Non requis',
    ],

    'bom_override' => [
        'disable' => 'Désactiver',
        'override_qty' => 'Remplacer la quantité',
        'add' => 'Ajouter',
    ],

    'substitute_status' => [
        'requested' => 'Demandé',
        'approved' => 'Approuvé',
        'rejected' => 'Rejeté',
    ],

    'procurement_status' => [
        'open' => 'Ouvert',
        'ordered' => 'Commandé',
        'closed' => 'Clôturé',
    ],

    'order' => [
        'title' => 'Ordres de fabrication',
        'subtitle' => 'Planifier, libérer et déclarer les ordres de fabrication/montage.',
        'empty' => 'Aucun ordre de fabrication.',
        'action' => [
            'create' => 'Créer un ordre',
            'release' => 'Libérer',
            'start' => 'Démarrer',
            'reserve' => 'Réserver le matériel',
            'report' => 'Déclarer',
            'deliver' => 'Livrer',
            'push_lexoffice' => 'Envoyer à Lexoffice',
            'subcontract' => 'Sous-traiter',
            'cancel' => 'Annuler',
        ],
        'field' => [
            'target_qty' => 'Quantité cible',
            'good' => 'Quantité conforme',
            'scrap' => 'Rebut',
            'rework' => 'Retouche',
            'produced' => 'Produit',
            'quantity' => 'Quantité',
            'materials' => 'Matériel',
            'reports' => 'Déclarations',
            'article' => 'Article',
            'deliveries' => 'Livraisons',
            'facturation_status' => 'Statut de facturation',
        ],
        'flash' => [
            'created' => 'Ordre créé.',
            'released' => 'Ordre libéré.',
            'started' => 'Ordre démarré.',
            'reserved' => 'Matériel réservé.',
            'reported' => 'Déclaration enregistrée.',
            'delivered' => 'Livré.',
            'lexoffice_pushed' => 'Bon de livraison envoyé à Lexoffice.',
            'subcontracted' => 'Confié au fournisseur (commande créée).',
            'subcontract_failed' => 'Sous-traitance impossible.',
            'cancelled' => 'Ordre annulé.',
            'deliver_needs_variant_warehouse' => 'La livraison nécessite une variante et un entrepôt.',
        ],
    ],
];
