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
];
