<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : enums.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'transaction_kind' => [
        'deposit' => 'Entrée',
        'withdrawal' => 'Sortie',
    ],
    'match_status' => [
        'unmatched' => 'Sans rattachement',
        'matched' => 'Rattaché',
        'ambiguous' => 'Ambigu',
    ],
];
