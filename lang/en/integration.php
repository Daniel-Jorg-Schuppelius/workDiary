<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'webhook' => [
        'title' => [
            'index' => 'Webhooks',
            'subtitle' => 'Outgoing event notifications to external systems.',
            'help' => 'How do webhooks work?',
            'help_text' => 'A webhook sends a signed JSON payload via HTTPS POST to your URL whenever a subscribed event occurs. The signature (HMAC-SHA256 over timestamp and body) is in the X-WorkDiary-Signature header; verify it with the signing key. After several failed attempts the endpoint is disabled automatically.',
            'create' => 'Create webhook',
            'edit' => 'Edit webhook',
            'empty' => 'No webhooks created yet.',
        ],
        'field' => [
            'basics' => 'Basics',
            'label' => 'Label',
            'label_placeholder' => 'e.g. ERP integration',
            'url' => 'Target URL',
            'url_help' => 'HTTPS endpoint that receives the POST request.',
            'events' => 'Subscribed events',
            'events_help' => 'Only selected events trigger a delivery.',
            'security' => 'Security & status',
            'signing_secret' => 'Signing key',
            'endpoint_active' => 'Endpoint active',
            'status' => 'Status',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'auto_disabled' => 'Auto-disabled',
            'auto_disabled_help' => 'Automatically disabled after too many failed attempts. Saving as active re-enables the endpoint.',
            'last_deliveries' => 'Recent deliveries',
            'no_deliveries' => 'No deliveries yet.',
        ],
        'action' => [
            'create' => 'Create',
            'edit' => 'Edit',
            'save' => 'Save',
            'delete' => 'Delete',
            'delete_confirm' => 'Really delete this webhook? Existing delivery logs are kept.',
            'rotate_secret' => 'Rotate signing key',
            'test' => 'Send test event',
        ],
        'secret' => [
            'shown_once' => 'Signing key – shown only now',
            'shown_once_help' => 'Copy the key now. For security reasons it is never shown in plaintext again.',
            'rotate_help' => 'The plaintext key is shown only once on creation/rotation.',
            'rotate_confirm' => 'Generate a new signing key? The old key becomes invalid immediately.',
        ],
        'flash' => [
            'created' => 'Webhook created.',
            'updated' => 'Webhook updated.',
            'deleted' => 'Webhook deleted.',
            'secret_rotated' => 'Signing key rotated.',
            'test_sent' => 'Test event queued.',
        ],
        'event' => [
            'openIssue.assigned' => 'Open issue assigned',
            'openIssue.overdue' => 'Open issue overdue',
            'safetyEvent.reported' => 'Safety event reported',
            'isms.incidentCritical' => 'Critical ISMS security incident',
            'timeCorrection.requested' => 'Working-time correction requested',
            'monthClosure.submitted' => 'Monthly closure submitted',
            'sla.breached' => 'SLA deadline breached',
            'document.expired' => 'Document expired',
            'invoice.issued' => 'Invoice issued',
            'invoice.paid' => 'Invoice paid',
            'timesheet.submitted' => 'Timesheet submitted',
            'ticket.created' => 'Ticket created',
            'ticket.closed' => 'Ticket closed',
            'protocol.signed' => 'Protocol signed',
            'purchaseOrder.ordered' => 'Purchase order placed',
        ],
        'delivery_status' => [
            'pending' => 'Pending',
            'success' => 'Success',
            'failed' => 'Failed',
        ],
    ],
    'external_type' => [
        'client' => 'Client',
        'client_id' => 'Customer ID',
        'contact' => 'Contact',
        'delivery_note' => 'Delivery note',
        'dunning' => 'Dunning',
        'entry' => 'Entry',
        'foreign_client' => 'External customer',
        'invoice' => 'Invoice',
        'order_confirmation' => 'Order confirmation',
        'project' => 'Project',
        'project_id' => 'Project ID',
        'pushed_entry' => 'Pushed entry',
        'quotation' => 'Quotation',
        'session' => 'Session',
        'user' => 'User',
        'voucher' => 'Voucher',
        'work_package' => 'Work package',
        'anydesk_id' => 'AnyDesk ID',
        'teamviewer_id' => 'TeamViewer ID',
    ],
    'outbox' => [
        'status' => [
            'pending' => 'Pending',
            'processing' => 'Processing',
            'confirmed' => 'Confirmed',
            'failed' => 'Failed',
            'compensation_required' => 'Compensation required',
        ],
    ],
];
