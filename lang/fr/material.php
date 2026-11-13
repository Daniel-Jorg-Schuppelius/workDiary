<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : material.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Material ↔ Artikel (MVP-904).
    'article' => [
        'label' => 'Article',
        'none' => '— aucun article —',
        'hint' => 'Via l\'article, le rapport fournisseurs attribue la consommation à un fournisseur.',
        'link' => 'Attribuer les articles',
        'link_hint' => 'Attribuer les matériaux sans article via le même SKU ou numéro d\'article ; les correspondances ambiguës restent ouvertes.',
        'linked' => ':count matériaux attribués à un article.',
    ],
    'stock' => [
        'insufficient' => 'Seulement :available :unit disponibles dans l\'entrepôt « :warehouse ». Pour une sortie en stock négatif, cochez « Autoriser le stock négatif ».',
        'negative_forbidden' => 'Seules les personnes disposant du droit « Autoriser les stocks négatifs » peuvent comptabiliser une sortie en stock négatif.',
        'serial_required' => 'L\'article est soumis au numéro de série. Sortez-le via l\'entrepôt avec son numéro de série, ou saisissez la consommation sans fiche matériel.',
        'booking_failed' => 'La sortie de stock n\'a pas pu être comptabilisée : :reason',
        'return_failed' => 'Le retour en stock n\'a pas pu être comptabilisé : :reason',
        'usage_gone' => 'La consommation de matériel liée à cette demande n\'existe plus.',
        'allow_negative_hint' => 'Nécessaire uniquement si un article géré en stock n\'a pas assez de stock dans l\'entrepôt par défaut.',
        'notice' => ':status · entrepôt « :warehouse »',
        'current' => 'Stock : :notice',
    ],
];
