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
    'no_options' => 'Aucune option définie.',
    'no_variants' => 'Aucune variante créée.',
    'sku_auto_hint' => 'attribué automatiquement',

    'action' => [
        'create' => 'Créer un article',
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
