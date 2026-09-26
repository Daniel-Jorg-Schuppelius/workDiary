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
        'none' => '— no article —',
        'hint' => 'Via the article, the supplier report assigns consumption to a supplier.',
        'link' => 'Assign articles',
        'link_hint' => 'Assign materials without an article via the same SKU or article number; ambiguous matches remain open.',
        'linked' => ':count materials assigned to an article.',
    ],
];
