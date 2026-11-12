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
    // Lagerbezug der Rückläufer (MVP-1576).
    'rma' => [
        'group_return' => 'Return',
        'group_receipt' => 'Goods receipt',
        'group_stock' => 'Stock reference',
        'hint_stock' => 'With warehouse, variant and quantity, the goods receipt posts the stock into quarantine. Without a variant, the return is only documented.',
        'hint_warehouse' => 'Required for the stock posting as soon as a variant is selected.',
        'hint_serial' => 'The selection sets the variant and quantity 1.',
        'option' => ['no_stock' => '— no stock item —'],
        'without_stock' => 'no stock reference — no stock posting',
        'no_stock_module' => 'The inventory module is not active. The return is recorded without a stock posting.',
        'disposition_books' => 'The decision transfers the stock out of quarantine or writes it off.',
        'disposition_documents' => 'This return has no stock reference. The decision is recorded without a stock posting.',
        'flash' => [
            'received_booked' => 'Goods receipt posted into quarantine (:state).',
            'received_unbooked' => 'Goods receipt recorded without a stock posting (no stock item or inventory module not active).',
            'disposition_booked' => 'Disposition posted.',
            'disposition_unbooked' => 'Disposition recorded without a stock posting.',
        ],
        'error' => [
            'serial_variant' => 'The serial number belongs to a different item variant.',
            'serial_qty' => 'A return with a serial number has quantity 1.',
            'lot_variant' => 'The lot belongs to a different item variant.',
        ],
    ],
    // Bezugsobjekte, Einstieg, Finanzfolge und Regress der Akte (MVP-1577).
    'form' => [
        'title_edit' => 'Edit claim',
        'lot_article_mismatch' => 'The batch belongs to a different article.',
        'group_links' => 'Affected objects',
        'hint_links' => 'Link what the claim refers to. With a customer selected, the lists show only that customer’s objects.',
        'hint_serial' => 'A serial number known in inventory is linked to the case.',
        'serial_linked' => 'Serial number is known in inventory and linked.',
    ],
    'open' => [
        'action' => 'Open claim',
    ],
    'financial' => [
        'hint_invoice_local' => 'Credit note, cancellation, price reduction and correction are created from this invoice. A cancellation requires an issued, unpaid invoice; a credit note requires a paid invoice.',
        'hint_invoice_external' => 'This customer’s invoices are managed in an external system. The correction document is created there; enter its number here afterwards.',
        'source_invoice' => 'for invoice :number',
        'error' => [
            'invoice_required' => 'Select the invoice the correction document is to be created from.',
            'not_cancellable' => 'Invoice :number cannot be cancelled. A cancellation requires an issued, unpaid invoice.',
            'not_creditable' => 'No credit note can be created for invoice :number. It must be paid and must not have a correction yet.',
            'not_rejectable' => 'Only proposed outcomes can be rejected.',
        ],
        'reject' => 'Reject',
        'reject_confirm' => 'Reject the proposal? It ends without a document; a new proposal remains possible.',
        'flash' => [
            'rejected' => 'Proposal rejected.',
        ],
    ],
    'recourse' => [
        'group_claim' => 'Claim',
        'group_reference' => 'Reference',
        'hint_due_days' => 'Without an entry, :days days from submission apply.',
        'hint_terms' => 'For example warranty period, exclusions or the notification period under the supply contract.',
        'field_incoming_invoice' => 'Incoming invoice',
        'due_days' => 'Response period: :days days from submission',
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
    'action' => [
        'follow_up_order' => 'Follow-up order',
        'follow_up_none' => '— no follow-up order —',
    ],
];
