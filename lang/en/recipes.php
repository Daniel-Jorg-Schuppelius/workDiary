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

// Recipes (MVP-455): brand-neutral recipe management + party service add-on.
return [
    'title' => [
        'materials' => 'Recipe / material requirements',
        'party' => 'Party service: base yield & portions',
        'allergen_overrides' => 'Allergen deviations (with justification)',
        'allergens' => 'Allergens',
        'plan' => 'Scaling & plan costs',
    ],
    'hint' => [
        'version' => 'Version :version',
        'readonly' => 'published — immutable state',
        'materials' => 'Fixed amounts, amounts per unit/portion or mixing ratios; tools stay separate from consumption. Positions can only be edited in draft versions.',
        'ratio_input' => 'For "ratio share" the value states the share of the target quantity (sum of all shares = total quantity).',
        'party' => 'Base yield documents how many portions the standard batch produces; per-unit amounts are recorded per portion.',
    ],
    'empty' => [
        'no_version' => 'No version yet — create a draft version first.',
        'no_materials' => 'No positions recorded yet.',
    ],
    'field' => [
        'position' => 'Pos.',
        'article' => 'Article/ingredient',
        'article_placeholder' => '… select article',
        'kind' => 'Quantity type',
        'quantity' => 'Quantity',
        'quantity_or_ratio' => 'Quantity / share',
        'unit' => 'Unit',
        'waste' => 'Waste %',
        'tool' => 'Tool',
        'tool_yes' => 'Tool',
        'actions' => 'Actions',
        'base_portions' => 'Base yield (portions)',
        'base_yield' => 'Output quantity',
        'yield_unit' => 'Output unit',
        'allergen_added' => 'Additionally declare',
        'allergen_removed' => 'Do not declare',
        'override_reason' => 'Justification for deviation',
        'portions' => 'Portions',
        'demand' => 'Demand',
        'cost' => 'Plan costs',
    ],
    'kind' => [
        'fixed' => 'fixed per batch',
        'per_unit' => 'per portion/unit',
        'ratio' => 'ratio share',
    ],
    'action' => [
        'add' => 'Add position',
        'remove' => 'Remove',
        'save_profile' => 'Save profile',
        'save_allergens' => 'Save allergens',
        'scale' => 'Scale',
        'back' => 'Back to overview',
    ],
    'allergens' => [
        'none' => 'No allergens declared.',
        'unresolved_heading' => 'Ingredients without allergen assignment',
    ],
    'plan' => [
        'total' => 'Total',
        'per_portion' => 'per portion',
    ],
    'flash' => [
        'material_saved' => 'Position saved.',
        'material_removed' => 'Position removed.',
        'profile_saved' => 'Recipe profile saved.',
        'allergens_saved' => 'Ingredient allergens saved.',
        'menu_saved' => 'Menu saved.',
    ],
    'error' => [
        'published_immutable' => 'Published recipe states are immutable — please create a new version.',
        'override_reason_required' => 'Allergen deviations require a justification.',
        'ratio_required' => 'Please enter a value greater than 0 for a ratio share.',
        'allergens_unresolved' => 'Publishing blocked: ingredients without allergen assignment (:articles). Assign allergens or record a justified deviation.',
    ],
    'costs' => [
        'unit_unmapped' => ':article: unit ":unit" cannot be converted to the base unit — costs incomplete.',
        'price_missing' => ':article: no purchase price recorded — costs incomplete.',
    ],
    'menu' => [
        'title' => 'Menu planning',
        'intro' => 'Menus and buffets from published recipes — the guest count scales the aggregated material demand.',
        'empty' => 'No menus created yet.',
        'no_date' => 'no date',
        'no_dishes' => 'No dishes in the menu yet.',
        'not_published' => 'no published state',
        'dishes_heading' => 'Dishes',
        'aggregate_heading' => 'Aggregated material demand',
        'missing_published' => 'Not included (no published recipe state): :dishes',
        'no_materials' => 'No material demand — add dishes with published recipe states.',
        'field' => [
            'name' => 'Name',
            'event_date' => 'Date',
            'guest_count' => 'Guest count',
            'dishes' => 'Dishes',
            'dish' => 'Dish',
            'dish_placeholder' => '… select dish',
            'portions_per_guest' => 'Portions per guest',
            'portions_total' => 'Total portions',
            'version' => 'Recipe state',
        ],
        'action' => [
            'create' => 'Create menu',
            'open' => 'Open',
            'add_dish' => 'Add dish',
        ],
    ],
];
