<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Notable patterns (serial defects, lots)',
        'hint' => 'Groups with at least :threshold claims in the period. A hint, not a decision.',
        'none' => 'No notable patterns in the period.',
        'rule_label' => 'Rule',
        'group' => 'Group',
        'cases' => 'Cases',
        'count' => 'Count',
        'rule' => [
            'lot' => 'Lot',
            'article_defect' => 'Article × defect type',
            'article_cause' => 'Article × cause',
            'supplier_defect' => 'Supplier × defect type',
            'entry_type_cause' => 'Order type × cause',
        ],
        'notify_title' => 'Notable claim pattern: :label',
        'notify_message' => ':count claims in :days days (:rule).',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Return label',
        'create' => 'Create return label',
        'download' => 'Download label',
        'created' => 'Return label created (shipment :tracking).',
        'no_address' => 'The return label needs the customer address (street, postcode, city).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Complaint',
            'empty' => 'No returns registered yet.',
            'rma' => 'Return number',
            'status' => 'Status',
            'title' => 'My returns',
        ],
        'capability' => 'Register a return',
        'nav' => 'Register a return',
        'title' => 'Register a return',
        'intro' => 'Choose the delivery or object, describe the reason and attach photos if needed. You will receive a return number; we provide a return label if required.',
        'empty' => 'There are no deliveries or objects for your account.',
        'submit' => 'Register return',
        'label' => 'Download return label',
        'field' => [
            'delivery' => 'Delivery',
            'asset' => 'Object',
            'serial_no' => 'Serial number',
            'quantity' => 'Quantity',
            'title' => 'Short description',
            'description' => 'Reason for return',
            'photos' => 'Photos or documents (max. 5)',
        ],
        'flash' => [
            'submitted' => 'Return registered: claim :number, return number :rma.',
        ],
        'error' => [
            'subject' => 'Please choose a delivery or an object.',
            'serial' => 'This serial number does not belong to the selected delivery.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Your subsequent submissions',
        'notification_title' => 'Subsequent submission for complaint :number',
    ],
];
