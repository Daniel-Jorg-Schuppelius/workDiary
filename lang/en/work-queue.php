<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work-queue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Work queue',
    'subtitle' => 'Everything you should work on now — from approvals, inboxes, tasks and reminders. You process each item on its own page.',
    'filter' => [
        'category' => 'Area',
        'all_categories' => 'All areas',
        'source' => 'Source',
        'all_sources' => 'All sources',
        'mine' => 'Assigned to me only',
    ],
    'column' => [
        'urgency' => 'Urgency',
        'item' => 'Item',
        'source' => 'Source',
        'due' => 'Due',
        'count' => 'Count',
        'assignment' => 'Responsibility',
    ],
    'assignment' => [
        'personal' => 'Assigned to you',
        'shared' => 'Through your role',
    ],
    'empty' => [
        'title' => 'Nothing to do',
        'message' => 'No item is waiting for you at the moment.',
    ],
    'source' => [
        'resale_plan' => 'Subscriptions & licences: recurring templates',
        'approval' => 'Approval chains',
        'vacation_approval' => 'Leave requests',
        'expense_approval' => 'Expenses',
        'month_closure' => 'Month closings',
        'price_change' => 'Price changes',
        'learning_time' => 'Learning time',
        'integration_inbox' => 'Assignment inbox',
        'invoice_inquiry' => 'Invoice inquiries',
        'incoming_invoice' => 'Incoming invoices',
        'open_issue' => 'Open issues',
        'assigned_entry' => 'Orders',
        'task' => 'Project tasks',
        'service_ticket' => 'Service tickets',
        'draft_reminder' => 'Your drafts',
        'chat_reminder' => 'Chat reminders',
    ],
    'item' => [
        'vacation' => 'Leave request from :name',
        'expense' => 'Expense from :name',
        'month_closure' => 'Month :period from :name',
        'price_change' => 'Price change: :article',
        'learning_time' => 'Learning time of :name',
        'integration_inbox' => 'Open assignments: :source',
        'invoice_inquiry' => 'Inquiry about invoice :number',
        'incoming_invoice' => [
            'assign' => 'Assign incoming invoices',
            'review' => 'Review incoming invoices',
            'failed' => 'Failed transfers',
            'duplicates' => 'Possible duplicate receipts',
        ],
        'entry_untitled' => 'Untitled order',
        'chat_reminder' => 'Chat reminder',
    ],
    'detail' => [
        'days_open' => ':days days not closed',
    ],
    'widget' => [
        'label' => 'Work queue',
        'description' => 'The most urgent open items from all areas.',
        'all' => 'Show all',
        'empty' => 'No item is waiting for you at the moment.',
    ],
];
