<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : stammdaten.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'identifier' => [
        'heading' => 'Master data needs review',
        'hint' => 'These entries fail validation. Correct them here — the change is pushed to the connected services.',
        'suggestion' => 'Suggestion: :value',
        'context' => [
            'bank_account' => 'Bank account “:label”',
            'variant' => 'Variant :label',
            'bank_account_fallback' => 'Bank account',
        ],
        'field' => [
            'vat_id' => 'VAT ID',
            'tax_number' => 'Tax number',
            'tax_identification_number' => 'Tax identification number',
            'bank_iban' => 'IBAN',
            'bank_bic' => 'BIC',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'gtin' => 'GTIN',
        ],
        'reason' => [
            'tax_number_in_vat_field' => 'Looks like a tax number but sits in the VAT ID field.',
            'vat_invalid' => 'Not a valid VAT ID (checksum mismatch).',
            'tax_number_too_short' => 'Too short for a German tax number (10 to 13 digits).',
            'tax_number_invalid' => 'Not a valid German tax number.',
            'tax_id_invalid' => 'Not a valid tax identification number (checksum mismatch).',
            'iban_invalid' => 'Not a valid IBAN (length or checksum mismatch).',
            'bic_invalid' => 'Not a valid BIC (8 or 11 characters allowed).',
            'gtin_invalid' => 'Not a valid GTIN (checksum mismatch).',
            'generic' => 'The value fails validation.',
        ],
    ],
];
