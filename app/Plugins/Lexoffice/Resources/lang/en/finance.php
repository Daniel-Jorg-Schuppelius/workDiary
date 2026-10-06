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
        'lexoffice_contact_missing' => 'No Lexoffice contact for the customer — please sync the contact first.',
        'lexoffice_delivery_no_customer' => 'A delivery without a customer cannot be handed over as a delivery note.',
        'lexoffice_delivery_not_linked' => 'No Lexoffice delivery note is linked to this delivery.',
        'lexoffice_dunning_not_invoice' => 'A dunning can only be created for an invoice.',
        'lexoffice_not_configured' => 'Lexoffice is not configured for this organisation (API key missing).',
        'lexoffice_outcome_unclear' => 'Outcome of the Lexoffice transfer is unclear (timeout after sending) — do not retry blindly; the next run looks for the draft via the source marker.',
        'lexoffice_oc_no_customer' => 'A manufacturing order without a customer cannot be handed over as an order confirmation.',
        'lexoffice_oc_not_linked' => 'No Lexoffice order confirmation is linked to this manufacturing order.',
        'lexoffice_quote_no_customer' => 'A manufacturing order without a customer cannot be handed over as a quotation.',
        'lexoffice_quote_not_linked' => 'No Lexoffice quotation is linked to this manufacturing order.',
    ],
    'lexoffice' => [
        'introduction' => 'We invoice the goods and services provided as follows.',
        'delivery_title' => 'Delivery note',
        'transfer_marker' => 'Transfer reference :marker',
    ],
];
