<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    // Die vier HOAI-Stufen (Feature 109, MVP-646) — sie lösen einander nicht
    // ab, ihr Vergleich ist die Kostenkontrolle.
    'stage' => [
        'estimate' => 'Cost estimate',
        'calculation' => 'Cost calculation',
        'quote' => 'Cost quotation',
        'final' => 'Final cost statement',
    ],
    // Woher die Zahlen stammen.
    'source' => [
        'x51_import' => 'imported from X51',
        'derived' => 'derived from own data',
        'manual' => 'entered manually',
    ],
];
