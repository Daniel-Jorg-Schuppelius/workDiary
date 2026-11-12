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
        'ownership_unavailable' => 'The connection is not set up — the state cannot be applied right now.',
        'ownership_not_ready' => 'The source is not providing the state right now — please apply it again later.',
        'easybill_no_customer' => 'The document has no customer and cannot be transferred to easybill.',
        'easybill_cancel_not_transferred' => 'Invoice :number is not in easybill; the cancellation cannot be created there. Set the range “Cancellation” to WorkDiary to number it locally.',
        'easybill_not_configured' => 'easybill is not configured for this organisation (API key missing).',
        'easybill_outcome_unclear' => 'Outcome of the easybill transfer unclear (timeout after sending) — do not blindly retry; the next run reconciles via the source marker.',
    ],
    'relations' => [
        'draft' => 'Draft',
        'done' => 'completed',
    ],
];
