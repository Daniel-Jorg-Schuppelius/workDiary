<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : recall.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Rückrufaktionen (MVP-921/922).
return [
    'title' => 'Recalls',
    'nav' => 'Recalls',
    'subtitle' => 'Recall per article variant: identify affected deliveries and customers, block stock, track status per customer.',
    'empty' => 'No recalls.',
    'items_none' => 'No affected deliveries.',
    'yes' => 'yes',
    'no' => 'no',
    'kpi' => [
        'active' => 'Active recalls',
    ],
    'filter' => [
        'all_status' => 'All statuses',
    ],
    'field' => [
        'number' => 'Number',
        'title' => 'Title',
        'variant' => 'Article variant',
        'kind' => 'Reason type',
        'open_items' => 'Open / affected',
        'status' => 'Status',
        'reason' => 'Reason and action',
        'customer_message' => 'Message to customers',
        'manufacturing_orders' => 'Manufacturing orders',
        'delivered_from' => 'Delivered from',
        'delivered_until' => 'Delivered until',
        'serial_numbers' => 'Serial numbers',
        'is_blocking_stock' => 'Block stock within the scope',
        'activated_at' => 'Activated on',
        'delivered_at' => 'Delivered on',
        'customer' => 'Customer',
        'quantity' => 'Quantity',
        'serial' => 'Serial number',
        'actions' => 'Actions',
        'claim' => 'Claim',
        'sent_at' => 'Sent on',
        'recipient' => 'Recipient',
    ],
    'hint' => [
        'customer_message' => 'Used in the customer portal and in the letter.',
        'scope' => 'Empty fields do not narrow the scope; all filled fields apply together.',
        'list' => 'Separated by commas or one per line.',
        'claim' => 'Open a claim with RMA for the return.',
    ],
    'section' => [
        'recall' => 'Recall',
        'scope' => 'Scope',
        'preview' => 'Preview of affected deliveries',
        'items' => 'Affected deliveries',
        'dispatches' => 'Delivery records',
    ],
    'preview' => [
        'summary' => ':deliveries deliveries affected, :stock serial numbers in stock',
        'none' => 'No delivery within this scope.',
    ],
    'action' => [
        'create' => 'Create recall',
        'show' => 'Show',
        'edit' => 'Edit',
        'save' => 'Save',
        'notify' => 'Notify customers',
        'claim' => 'Claim',
    ],
    'dialog' => [
        'create' => 'Create recall',
        'edit' => 'Edit recall',
    ],
    'transition' => [
        'active' => 'Activate',
        'completed' => 'Complete',
        'cancelled' => 'Cancel',
    ],
    'confirm' => [
        'active' => 'Activate the recall? Affected deliveries are fixed and stock within the scope is blocked.',
        'completed' => 'Complete the recall?',
        'cancelled' => 'Cancel the recall? The blocks of this recall are lifted.',
        'notify' => 'Email all customers with open items?',
    ],
    'item_transition' => [
        'notified' => 'Notified',
        'returned' => 'Returned',
        'resolved' => 'Resolved',
    ],
    'status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
    'item_status' => [
        'open' => 'Open',
        'notified' => 'Notified',
        'returned' => 'Returned',
        'resolved' => 'Resolved',
    ],
    'kind' => [
        'safety' => 'Safety',
        'quality' => 'Quality',
        'regulatory' => 'Regulatory requirement',
    ],
    'error' => [
        'not_draft' => 'Only drafts can be changed.',
        'not_active' => 'Notifications are only possible for active recalls.',
    ],
    'flash' => [
        'created' => 'Recall :number created.',
        'saved' => 'Recall saved.',
        'status' => 'Status: :status.',
        'item' => 'Status saved.',
        'notified' => ':count customers notified.',
        'without_email' => 'No valid email, please inform otherwise: :customers',
        'claim' => 'Claim :number opened for the return.',
    ],
    'stats' => [
        'return_rate' => 'Return rate',
    ],
    'dispatch' => [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'none' => 'No notifications sent yet.',
    ],
    'mail' => [
        'subject' => 'Recall: :title (:number)',
        'body' => "Dear :name,\n\nwe are recalling the following product: :product.\n\n:message",
        'default_message' => 'Please stop using the product and contact us; we will arrange a return or replacement.',
        'serials' => 'Affected serial numbers: :serials',
    ],
    'claim' => [
        'title' => 'Recall :number: :title',
    ],
    'portal' => [
        'subject' => 'Recall: :title (:product)',
    ],
    // Behördenmeldung (MVP-945).
    'authority' => [
        'title' => 'Authority notification',
        'save' => 'Save',
        'pdf' => 'Report form',
        'pdf_title' => 'Recall report form',
        'pdf_note' => 'Compilation of the information for the notification to market surveillance; the notification itself is made in the portal of the competent authority.',
        'section' => [
            'product' => 'Product',
            'hazard' => 'Hazard and measure',
            'scope' => 'Scope',
            'authority' => 'Authority',
        ],
        'field' => [
            'product' => 'Product',
            'gtin' => 'GTIN',
            'batches' => 'Serial numbers',
            'delivered' => 'Delivery period',
            'hazard_kind' => 'Hazard type',
            'hazard_description' => 'Hazard description',
            'risk_level' => 'Risk level',
            'measure' => 'Measure',
            'countries' => 'Countries of distribution',
            'units' => 'Affected units',
            'customers' => 'Affected customers',
            'returned' => 'Returns',
            'activated_at' => 'Recall since',
            'authority_name' => 'Authority',
            'authority_reference' => 'Reference',
            'authority_reported_on' => 'Reported on',
            'contact' => 'Contact',
            'contact_name' => 'Contact person',
            'contact_email' => 'Contact email',
        ],
        'hint' => [
            'hazard_kind' => 'e.g. fire, electric shock, injury, chemical',
            'countries' => 'Country codes, comma-separated (DE, AT, …)',
        ],
        'risk' => [
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'serious' => 'serious',
        ],
        'measure' => [
            'withdrawal' => 'Withdrawal from the market',
            'recall' => 'Recall from end users',
            'warning' => 'Warning',
            'destruction' => 'Destruction',
        ],
        'flash' => [
            'saved' => 'Details saved.',
        ],
    ],
];
