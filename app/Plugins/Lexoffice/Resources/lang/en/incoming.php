<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rechnungseingang → Lexware Office (Feature 163, MVP-1111).

return [
    'label' => 'Lexware Office',
    'amount_differs' => 'Lexware already has voucher :number with a different amount. Please check it there and then start the handover again.',
    'remark' => 'From the WorkDiary incoming invoices (:sender).',
    'no_party' => 'The document is not yet assigned to a party.',
    'contact_missing' => 'The contact :party could neither be found nor created in Lexware.',
    'contact_role' => 'Lexware rejects the contact :party because of its role. Please add the vendor or customer role in Lexware and then start the handover again.',
    'header_only' => [
        'no_category' => 'Handed over without amounts: no posting category is set for the party or in the settings.',
        'currency' => 'Handed over without amounts: Lexware only accepts vouchers in euros.',
        'rates' => 'Handed over without amounts: the tax rates do not match Lexware or the totals are incomplete.',
    ],
    'settings' => [
        'transfer' => 'Hand over incoming invoices to Lexware Office',
        'transfer_help' => 'Assigned documents go to Lexware as “to be checked”. If a voucher with the same number already exists there, it is only linked.',
        'incoming_category' => 'Default category for incoming documents',
        'outgoing_category' => 'Default category for outgoing documents',
        'category_help' => 'Without a category, vouchers go to Lexware without amounts. A category on the supplier or customer takes precedence. The list comes from the category sync.',
        'no_category' => '— none —',
    ],
    'category' => [
        'title' => 'Posting category in Lexware',
        'help' => 'Documents from incoming invoices go to Lexware with this category instead of the default from the settings.',
        'save' => 'Save category',
        'saved' => 'Posting category saved.',
        'cleared' => 'Posting category removed; the default from the settings applies.',
    ],
];
