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
        'label' => 'Artikel',
        'none' => '— kein Artikel —',
        'hint' => 'Über den Artikel ordnet die Lieferantenauswertung den Verbrauch einem Lieferanten zu.',
        'link' => 'Artikel zuordnen',
        'link_hint' => 'Materialien ohne Artikel über gleiche SKU bzw. Artikelnummer zuordnen; mehrdeutige Treffer bleiben offen.',
        'linked' => ':count Materialien einem Artikel zugeordnet.',
    ],
];
