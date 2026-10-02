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
    'error' => [
        'sevdesk_not_configured' => 'sevDesk is not configured for this organisation (API token missing).',
        'sevdesk_outcome_unclear' => 'Outcome of the sevDesk handover unclear (timeout after sending) — do not retry blindly; the next run reconciles via the source marker.',
    ],
    'sevdesk' => [
        'introduction' => 'We invoice the goods and services provided in the period :from – :to as follows.',
        'tax_text' => 'VAT :rate%',
    ],
];
