<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer-billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'channel_not_configured' => ':system is not enabled or not fully set up.',
    'channel_payment_note' => 'Document :number (:system)',
    'retainer_line' => 'Monthly retainer :period',
    'retainer_only' => 'Only available in retainer mode.',
    'retainer_voucher_already_linked' => 'An invoice in :system is already linked to this month — sending would create a second document there.',
    'trueup_already_open' => 'An unpaid settlement invoice is already open.',
    'trueup_line' => 'Balance settlement as of :date',
    'trueup_no_open_balance' => 'No outstanding balance — no settlement needed.',
    'voucher_not_found' => 'The selected document was not found.',
];
