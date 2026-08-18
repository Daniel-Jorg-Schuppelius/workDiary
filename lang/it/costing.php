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
        'estimate' => 'Stima dei costi',
        'calculation' => 'Calcolo dei costi',
        'quote' => 'Preventivo dei costi',
        'final' => 'Consuntivo dei costi',
    ],
    // Woher die Zahlen stammen.
    'source' => [
        'x51_import' => 'acquisito da X51',
        'derived' => 'derivato dai dati propri',
        'manual' => 'inserito manualmente',
    ],
];
