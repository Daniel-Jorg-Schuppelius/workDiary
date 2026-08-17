<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : article.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Articles',
    'subtitle' => 'Référentiel d’articles canonique du locataire (produits, matériel, services).',
    'empty' => 'Aucun article créé pour le moment.',
    'variants' => 'Variantes',
    'options' => 'Options',
    'units' => 'Unités',
    'external_mappings' => 'Correspondances externes',
    'supplies' => [
        'title' => 'Sources d\'approvisionnement',
        'supplier' => 'Fournisseur',
        'sku' => 'Réf. art. fourn.',
        'price' => 'Prix d\'achat',
        'lead_time' => 'Délai',
        'moq' => 'Qté min.',
        'days' => 'jours',
        'preferred' => 'Préféré',
        'recommended' => 'Recommandé',
        'set_preferred' => 'Définir comme préféré',
        'flash' => ['preferred_set' => 'Source d\'approvisionnement préférée définie.', 'datanorm_empty' => 'Aucun article exportable (actif et vendable) disponible.'],
    ],
    'no_options' => 'Aucune option définie.',
    'no_variants' => 'Aucune variante créée.',
    'sku_auto_hint' => 'attribué automatiquement',

    'datanorm_oversized' => ':count numéro d\'article dépasse 15 caractères et est exclu de l\'export DATANORM.|:count numéros d\'article dépassent 15 caractères et sont exclus de l\'export DATANORM.',

    'discount_group' => [
        'title' => 'Groupes de remise de vente',
        'hint' => 'Conditions standard de l\'organisation pour les exports DATANORM avec prix catalogue : les destinataires calculent liste − remise = net. Les prix par client passent par le DATPREIS B2B.',
        'empty' => 'Aucun groupe de remise pour le moment.',
        'confirm_delete' => 'Supprimer ce groupe de remise ? Les affectations d\'articles seront retirées.',
        'kind' => ['discount' => 'Remise (%)', 'factor' => 'Facteur', 'surcharge' => 'Majoration (%)'],
        'col' => ['code' => 'Code', 'kind' => 'Type', 'value' => 'Valeur', 'label' => 'Libellé', 'articles' => 'Articles'],
        'action' => ['add' => 'Créer', 'delete' => 'Supprimer'],
        'flash' => ['created' => 'Groupe de remise créé.', 'deleted' => 'Groupe de remise supprimé.', 'override_saved' => 'Dérogation client enregistrée.', 'override_deleted' => 'Dérogation client supprimée.'],
        'override' => [
            'title' => 'Dérogations client',
            'hint' => 'Taux spécifiques par client et groupe de remise — appliqués dans le DATPREIS B2B du client ; un custom_price d\'article reste prioritaire.',
            'customer' => 'Client',
            'empty' => 'Aucune dérogation client pour le moment.',
        ],
    ],

    'action' => [
        'create' => 'Créer un article',
        'export_datanorm' => 'Export DATANORM',
        'export_datanorm_v5_list' => 'DATANORM 5 — PV comme prix catalogue',
        'export_datanorm_v5_net' => 'DATANORM 5 — PV comme prix net',
        'export_datanorm_v4_list' => 'DATANORM 4 — PV comme prix catalogue',
        'export_datpreis_title' => 'Fichier de prix (DATPREIS)',
        'export_datpreis_v5' => 'DATPREIS 5 — PV actuels',
        'export_datpreis_v4' => 'DATPREIS 4 — PV actuels',
        'export_datpreis_since' => 'DATPREIS 5 — modifications des 30 derniers jours',
        'export_datpreis_custom' => 'DATPREIS depuis une date',
        'edit' => 'Modifier l’article',
        'retire' => 'Désactiver',
        'add_option' => 'Ajouter une option',
        'add_value' => 'Valeur',
        'add_variant' => 'Créer une variante',
        'add_unit' => 'Ajouter une unité',
    ],

    'field' => [
        'sku' => 'Numéro d’article (SKU)',
        'type' => 'Type d’article',
        'status' => 'Statut',
        'base_unit' => 'Unité de base',
        'category' => 'Groupe de marchandises',
        'category_hint' => 'Pour les rapports et l\'export DATANORM (fichier WRG).',
        'subcategory' => 'Sous-groupe de marchandises',
        'sales_discount_group' => 'Groupe de remise de vente',
        'sales_discount_group_hint' => 'Pour les exports DATANORM avec prix catalogue (fichier RAB).',
        'assembly_minutes' => 'Temps de montage (minutes par unité)',
        'assembly_minutes_hint' => 'Temps de travail calculé ; rempli depuis les enregistrements ARBA lors de l\'adoption DATANORM.',
        'gtin' => 'GTIN',
        'default_purchase_price' => 'Prix d’achat (par défaut)',
        'default_sale_price' => 'Prix de vente (par défaut)',
        'currency' => 'Devise',
        'code' => 'Code',
        'label' => 'Libellé',
        'option_name' => 'Nom de l’option',
        'combination' => 'Combinaison',
        'sale_price' => 'Prix de vente',
        'unit_kind' => 'Type',
        'factor_to_base' => 'Facteur vers l’unité de base',
        'external_id' => 'ID externe',
        'sync_status' => 'Statut de synchronisation',
    ],

    'group' => [
        'pricing' => 'Prix',
        'flags' => 'Propriétés',
    ],

    'flag' => [
        'stockable' => 'Stockable',
        'purchasable' => 'Achetable',
        'sellable' => 'Vendable',
        'manufacturable' => 'Fabricable',
        'batch_required' => 'Suivi par lot',
        'serial_required' => 'Suivi par numéro de série',
        'shelf_life_required' => 'Durée de conservation requise',
    ],

    'type' => [
        'raw' => 'Matière première',
        'consumable' => 'Consommable',
        'merchandise' => 'Marchandise',
        'semifinished' => 'Produit semi-fini',
        'finished' => 'Produit fini',
        'service' => 'Prestation',
    ],

    'status' => [
        'draft' => 'Brouillon',
        'active' => 'Actif',
        'retired' => 'Désactivé',
    ],

    'unit_kind' => [
        'base' => 'Base',
        'purchase' => 'Achat',
        'sale' => 'Vente',
        'packaging' => 'Conditionnement',
    ],

    'confirm' => [
        'retire' => 'Vraiment désactiver cet article ? Les variantes seront également désactivées.',
        'delete' => 'Supprimer définitivement cet article ? Seuls les brouillons sans référence peuvent être supprimés.',
    ],

    'flash' => [
        'created' => 'Article créé.',
        'updated' => 'Article mis à jour.',
        'deleted' => 'Article supprimé.',
        'retired' => 'Article désactivé.',
        'delete_blocked' => 'Impossible de supprimer l’article : seuls les brouillons sans référence sont supprimables. Veuillez plutôt le désactiver.',
        'option_added' => 'Option ajoutée.',
        'value_added' => 'Valeur d’option ajoutée.',
        'unit_added' => 'Unité ajoutée.',
        'variant_added' => 'Variante créée.',
        'variant_retired' => 'Variante désactivée.',
    ],
];
