<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recipes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Recettes (MVP-455) : gestion de recettes neutre + extension traiteur.
return [
    'title' => [
        'materials' => 'Recette / besoins en matières',
        'party' => 'Traiteur : rendement de base & portions',
        'allergen_overrides' => 'Écarts allergènes (avec justification)',
        'allergens' => 'Allergènes',
        'plan' => 'Mise à l\'échelle & coûts prévisionnels',
    ],
    'hint' => [
        'version' => 'Version :version',
        'readonly' => 'publié — état immuable',
        'materials' => 'Quantités fixes, quantités par unité/portion ou proportions de mélange ; les outils restent séparés de la consommation. Les positions ne sont modifiables que dans les versions brouillon.',
        'ratio_input' => 'Pour « part proportionnelle », la valeur indique la part de la quantité cible (somme des parts = quantité totale).',
        'party' => 'Le rendement de base documente combien de portions produit la préparation standard ; les quantités par unité sont saisies par portion.',
    ],
    'empty' => [
        'no_version' => 'Pas encore de version — créez d\'abord une version brouillon.',
        'no_materials' => 'Aucune position saisie.',
    ],
    'field' => [
        'position' => 'Pos.',
        'article' => 'Article/ingrédient',
        'article_placeholder' => '… choisir un article',
        'kind' => 'Type de quantité',
        'quantity' => 'Quantité',
        'quantity_or_ratio' => 'Quantité / part',
        'unit' => 'Unité',
        'waste' => 'Perte %',
        'tool' => 'Outil',
        'tool_yes' => 'Outil',
        'actions' => 'Actions',
        'base_portions' => 'Rendement de base (portions)',
        'base_yield' => 'Quantité produite',
        'yield_unit' => 'Unité produite',
        'allergen_added' => 'Déclarer en plus',
        'allergen_removed' => 'Ne pas déclarer',
        'override_reason' => 'Justification de l\'écart',
        'portions' => 'Portions',
        'demand' => 'Besoin',
        'cost' => 'Coûts prévisionnels',
    ],
    'kind' => [
        'fixed' => 'fixe par préparation',
        'per_unit' => 'par portion/unité',
        'ratio' => 'part proportionnelle',
    ],
    'action' => [
        'add' => 'Ajouter une position',
        'remove' => 'Retirer',
        'save_profile' => 'Enregistrer le profil',
        'save_allergens' => 'Enregistrer les allergènes',
        'scale' => 'Mettre à l\'échelle',
        'back' => 'Retour à l\'aperçu',
    ],
    'allergens' => [
        'none' => 'Aucun allergène déclaré.',
        'unresolved_heading' => 'Ingrédients sans attribution d\'allergènes',
    ],
    'plan' => [
        'total' => 'Total',
        'per_portion' => 'par portion',
    ],
    'flash' => [
        'material_saved' => 'Position enregistrée.',
        'material_removed' => 'Position retirée.',
        'profile_saved' => 'Profil de recette enregistré.',
        'allergens_saved' => 'Allergènes de l\'ingrédient enregistrés.',
        'menu_saved' => 'Menu enregistré.',
    ],
    'error' => [
        'published_immutable' => 'Les états publiés sont immuables — créez une nouvelle version.',
        'override_reason_required' => 'Les écarts allergènes nécessitent une justification.',
        'ratio_required' => 'Veuillez saisir une valeur supérieure à 0 pour une part proportionnelle.',
        'allergens_unresolved' => 'Publication bloquée : ingrédients sans attribution d\'allergènes (:articles). Attribuez les allergènes ou saisissez un écart justifié.',
    ],
    'costs' => [
        'unit_unmapped' => ':article : unité « :unit » non convertible en unité de base — coûts incomplets.',
        'price_missing' => ':article : aucun prix d\'achat renseigné — coûts incomplets.',
    ],
    'menu' => [
        'title' => 'Planification de menus',
        'intro' => 'Menus et buffets à partir de recettes publiées — le nombre d\'invités met à l\'échelle le besoin agrégé.',
        'empty' => 'Aucun menu créé.',
        'no_date' => 'sans date',
        'no_dishes' => 'Aucun plat dans le menu.',
        'not_published' => 'aucun état publié',
        'dishes_heading' => 'Plats',
        'aggregate_heading' => 'Besoin en matières agrégé',
        'missing_published' => 'Non pris en compte (pas d\'état publié) : :dishes',
        'no_materials' => 'Aucun besoin — ajoutez des plats avec des recettes publiées.',
        'field' => [
            'name' => 'Nom',
            'event_date' => 'Date',
            'guest_count' => 'Nombre d\'invités',
            'dishes' => 'Plats',
            'dish' => 'Plat',
            'dish_placeholder' => '… choisir un plat',
            'portions_per_guest' => 'Portions par invité',
            'portions_total' => 'Portions au total',
            'version' => 'État de recette',
        ],
        'action' => [
            'create' => 'Créer un menu',
            'open' => 'Ouvrir',
            'add_dish' => 'Ajouter un plat',
        ],
    ],
];
