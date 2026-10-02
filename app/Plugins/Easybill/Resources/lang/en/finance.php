<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'easybill' => [
        'introduction' => 'We invoice the goods and services provided in the period :from – :to as follows.',
        'unit_hour' => 'hrs',
        'unit_piece' => 'pcs',
    ],
    'error' => [
        'easybill_not_configured' => 'easybill is not configured for this organisation (API key missing).',
        'easybill_outcome_unclear' => 'Outcome of the easybill transfer unclear (timeout after sending) — do not blindly retry; the next run reconciles via the source marker.',
    ],
];
