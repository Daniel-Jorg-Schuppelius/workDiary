<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'feed' => [
        'title' => 'Document flow',
        'subtitle' => 'Quotes, invoices, vouchers and expenses for :range — adjustable via the date filter in the header.',
        'empty' => 'No documents in the selected period',
        'search_placeholder' => 'Number, customer, supplier …',
        'days_short' => 'd',
        'dunning_level' => 'Dunning level :level',
        'action' => [
            'dun' => 'Send reminder',
            'open_original' => 'Open original',
        ],
        'tab' => [
            'all' => 'All',
            'quotes' => 'Quotes',
            'outgoing' => 'Sales invoices',
            'incoming' => 'Purchase invoices',
            'credits' => 'Credit notes',
            'expenses' => 'Expenses',
            'other' => 'Other',
        ],
        'kpi' => [
            'revenue' => 'Revenue',
            'expense' => 'Cost (external)',
            'balance' => 'Balance',
            'internal_mine' => 'My expenses',
            'internal_all' => 'Expenses (all)',
            'internal_pending' => 'of which pending: :amount',
            'open' => 'Open',
            'overdue' => 'of which overdue',
            'open_count' => '{0} no open document|{1} :count open document|[2,*] :count open documents',
            'overdue_count' => ':count of :total documents',
            'neutral' => 'No monetary effect',
            'neutral_hint' => 'Quotes, order confirmations and delivery notes are counted only.',
        ],
        'filter' => [
            'direction' => 'Direction',
            'project' => 'Project',
            'origin' => 'Origin',
            'only_overdue' => 'Overdue only',
            'only_unlinked' => 'Unlinked only',
            'with_archived' => 'Include archived',
        ],
        'state' => [
            'draft' => 'Draft',
            'open' => 'Open',
            'paid' => 'Closed',
            'cancelled' => 'Cancelled',
        ],
        'scope' => [
            'mine' => 'Mine',
            'all' => 'All',
        ],
        'column' => [
            'kind' => 'Type',
            'origin' => 'Origin',
            'due' => 'Due',
            'open' => 'Open',
        ],
    ],
    'relations' => [
        'title' => 'Related documents (:count)',
        'column' => [
            'document' => 'Document',
            'number' => 'Number',
            'date' => 'Date',
            'state' => 'Status',
            'system' => 'System',
            'relation' => 'Relation',
        ],
        'via' => 'via :document :number',
        'schedule' => 'Billing plan',
        'quote_version' => 'Quote (version :version)',
        'manufacturing_order' => 'Manufacturing order',
        'transfer' => 'Handover (:channel)',
        'draft' => 'Draft',
    ],
    // Delivery note as a document of its own (phase 145, MVP-1163).
    'delivery_note' => [
        'create' => 'Create delivery note',
        'edit' => 'Edit quantities',
        'edit_hint' => 'For a partial delivery, adjust the quantity; 0 removes the item from this delivery note.',
        'details' => 'Delivery',
        'deliver' => 'Deliver',
        'cancel' => 'Cancel',
        'cancel_confirm' => 'Cancel the delivery note? It remains in the accounting program.',
        'delivered_on' => 'Delivered on :date',
        'to_order' => 'Manufacturing order :number',
        'number_pending' => ':program assigns the number on delivery; until then the delivery note carries a draft number.',
        'flash' => [
            'updated' => 'Delivery note saved.',
            'delivered' => 'Delivery note :number delivered.',
            'cancelled' => 'Delivery note cancelled.',
            'issue_failed' => 'Delivered, but delivery note :number remains a draft: :error',
        ],
        'error' => [
            'not_confirmed' => 'A delivery note can only be created from a confirmed order confirmation.',
            'fully_delivered' => 'All items of the order confirmation have already been delivered.',
            'not_editable' => 'Only drafts without a stock delivery can be edited.',
            'not_draft' => 'Only drafts can be delivered.',
            'empty' => 'The delivery note has no items.',
            'not_cancellable' => 'This delivery note cannot be cancelled. Correct a stock delivery via the stock issue.',
            'not_delivered' => 'The delivery note has not been delivered yet.',
        ],
    ],
];
