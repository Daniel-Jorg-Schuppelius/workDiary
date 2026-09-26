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
        'label' => 'Articolo',
        'none' => '— nessun articolo —',
        'hint' => 'Tramite l\'articolo, il report fornitori assegna il consumo a un fornitore.',
        'link' => 'Assegna articoli',
        'link_hint' => 'Assegnare i materiali senza articolo tramite lo stesso SKU o numero articolo; le corrispondenze ambigue restano aperte.',
        'linked' => ':count materiali assegnati a un articolo.',
    ],
];
