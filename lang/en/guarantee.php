<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : guarantee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Bürgschaftsregister (Feature 114, MVP-603).
return [
    'title' => 'Guarantees',
    'subtitle' => 'Issued and received guarantees with expiry and return record',
    'empty' => 'No guarantee recorded yet.',
    'unlimited' => 'unlimited',
    'created' => 'Guarantee recorded.',
    'updated' => 'Guarantee updated.',
    'returned' => 'Return recorded.',
    'drawn' => 'Drawing recorded.',
    'secured' => 'Retention replaced by the guarantee.',
    'not_active' => 'This guarantee is no longer active.',
    'retention_not_open' => 'This retention is no longer open.',
    'foreign_organization' => 'Guarantee and retention belong to different organizations.',
    'amount_too_low' => 'The guarantee does not cover the retention — a smaller guarantee does not replace it.',
    'issuer_hint' => 'Bank or insurer as stated on the certificate; alternatively pick a supplier record.',
    'issuer_supplier' => 'Guarantor from master data',
    'action' => [
        'create' => 'Record guarantee',
        'edit' => 'Edit guarantee',
        'returned' => 'Certificate returned',
    ],
    'kpi' => [
        'issued' => 'Issued (active)',
        'issued_hint' => 'While it is not returned, the aval fee keeps running.',
        'received' => 'Received (active)',
        'received_hint' => 'If it expires unnoticed, the security is gone.',
        'expiring' => 'Expiring within 90 days',
        'return_due' => 'Return due',
        'return_due_hint' => 'The replaced retention is released — the certificate belongs back.',
    ],
    'column' => [
        'reference' => 'Guarantee no.',
        'direction' => 'Direction',
        'kind' => 'Type',
        'issuer' => 'Guarantor',
        'party' => 'Counterparty',
        'amount' => 'Amount',
        'issued_on' => 'Issued on',
        'expires_on' => 'Expires on',
        'status' => 'Status',
        'customer' => 'Customer',
        'supplier' => 'Supplier',
        'project' => 'Project',
        'responsible' => 'Owner',
        'note' => 'Note',
    ],
    'filter' => [
        'direction' => 'Direction',
        'status' => 'Status',
    ],
];
