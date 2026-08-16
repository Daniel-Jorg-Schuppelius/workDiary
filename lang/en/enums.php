<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : enums.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'billbee' => [
        'order_state' => [
            1 => 'Ordered',
            2 => 'Confirmed',
            3 => 'Paid',
            4 => 'Shipped',
            5 => 'Complaint',
            6 => 'Deleted',
            7 => 'Completed',
            8 => 'Canceled',
            9 => 'Archived',
            11 => '1st reminder',
            12 => '2nd reminder',
            13 => 'Packed',
            14 => 'Offered',
            15 => 'Payment reminder',
            16 => 'In fulfillment',
        ],
    ],
    'ai' => [
        'family' => ['llm' => 'Language model (LLM)', 'translation' => 'Translation'],
        'verb' => ['formulate' => 'Formulate', 'summarize' => 'Summarize', 'classify' => 'Classify', 'explain' => 'Explain', 'find' => 'Find', 'translate' => 'Translate', 'extract' => 'Extract'],
        'provider' => ['anthropic' => 'Anthropic Claude', 'openai' => 'OpenAI', 'gemini' => 'Google Gemini', 'azure_openai' => 'Azure OpenAI', 'openai_compatible' => 'OpenAI-compatible (generic)', 'ollama' => 'Ollama (local)', 'deepl' => 'DeepL', 'azure_translator' => 'Azure Translator', 'google_translate' => 'Google Cloud Translation', 'libretranslate' => 'LibreTranslate (local)', 'fake' => 'Test provider'],
        'connection_status' => ['draft' => 'Draft', 'active' => 'Active', 'blocked' => 'Blocked'],
        'memory_type' => ['glossary' => 'Glossary', 'style_rule' => 'Style rule', 'example' => 'Example pair'],
        'sensitivity' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
    ],
    'domain' => [
        'environment' => ['ote' => 'OT&E (test/pilot)', 'production' => 'Production'],
        'connection_status' => ['draft' => 'Draft', 'active' => 'Active', 'blocked' => 'Blocked'],
        'sync_status' => ['current' => 'Current', 'stale' => 'Stale', 'pending' => 'Pending', 'conflict' => 'Conflict', 'unknown' => 'Unclear'],
        'renewal_mode' => ['autorenew' => 'Auto-renew', 'autoexpire' => 'Auto-expire', 'autodelete' => 'Auto-delete', 'renewonce' => 'Renew once'],
        'command_status' => ['draft' => 'Draft', 'approved' => 'Approved', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'failed' => 'Failed', 'unknown' => 'Unclear', 'conflict' => 'Conflict'],
        'capability_area' => ['authentication' => 'Authentication', 'subuser' => 'Subuser', 'domains' => 'Domains', 'contacts' => 'Contacts', 'nameservers' => 'Nameservers', 'dns' => 'DNS zones', 'events' => 'Events', 'renewal' => 'Renewal', 'transfer' => 'Transfer', 'accounting' => 'Accounting', 'invoices' => 'Invoices'],
    ],
    'asset' => [
        'defect-severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'defect-status' => [
            'open' => 'Open',
            'inRepair' => 'In repair',
            'resolved' => 'Resolved',
            'writtenOff' => 'Written off',
        ],
        'ownership' => [
            'org' => 'Organization',
            'customer' => 'Customer',
            'external' => 'External',
        ],
    ],
    'classification' => [
        'requirement-phase' => [
            'onCreate' => 'On creation',
            'beforeComplete' => 'Before completion',
            'beforeSign' => 'Before signing',
        ],
        'requirement-severity' => [
            'hard' => 'Blocking',
            'soft' => 'Notice',
        ],
    ],
    'room_requirement_kind' => [
        'hygieneLevel' => 'Hygiene level',
        'specialCleaning' => 'Special cleaning',
        'accessRestriction' => 'Access restriction',
        'itInventory' => 'IT inventory',
        'technicalInspection' => 'Technical inspection',
        'operatorDuty' => 'Operator duty',
        'other' => 'Other',
    ],
    'event' => [
        'type' => [
            'training' => 'Training',
            'workshop' => 'Workshop',
            'conference' => 'Conference',
            'meeting' => 'Meeting',
            'internal_briefing' => 'Internal briefing',
            'external_visit' => 'External visit',
        ],
        'status' => [
            'planned' => 'Planned',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'visibility' => [
            'internal' => 'Internal',
            'external' => 'External',
            'public' => 'Public',
        ],
        'participant' => [
            'role' => [
                'organizer' => 'Organizer',
                'trainer' => 'Trainer',
                'attendee' => 'Attendee',
                'optional' => 'Optional',
            ],
            'status' => [
                'invited' => 'Invited',
                'accepted' => 'Accepted',
                'declined' => 'Declined',
                'attended' => 'Attended',
                'no_show' => 'No-show',
            ],
        ],
        'reminder' => [
            'channel' => [
                'mail' => 'Email',
                'webpush' => 'Push',
                'database' => 'In-App',
            ],
        ],
    ],
    'vehicle' => [
        'type' => [
            'car' => 'Car',
            'van' => 'Van',
            'truck' => 'Truck',
            'bicycle' => 'Bicycle',
            'other' => 'Other',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Petrol',
            'gas' => 'Gas',
            'hybrid' => 'Hybrid',
            'electric' => 'Electric',
            'muscle' => 'Muscle power',
            'other' => 'Other',
        ],
        'ownership' => [
            'owned' => 'Owned',
            'leased' => 'Leased',
            'rental' => 'Rental',
        ],
    ],
    'diary' => [
        'dispatch_status' => [
            'unplanned' => 'Unplanned',
            'planned' => 'Planned',
            'confirmed' => 'Confirmed',
            'enRoute' => 'En route',
            'done' => 'Done',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Initial certificate',
            'follow_up' => 'Follow-up certificate',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Draft',
            'planned' => 'Planned',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Administration',
            'training' => 'Training',
            'meeting' => 'Meeting',
            'internal' => 'Internal',
            'travel' => 'Travel',
            'break' => 'Break',
            'absence' => 'Absence',
            'standby' => 'Standby',
            'other' => 'Other',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Vacation',
            'sick' => 'Sick',
            'special' => 'Special leave',
            'unpaid' => 'Unpaid',
        ],
        'status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ],
    ],
    'cloud_intake' => [
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'connection_status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'reauth_required' => 'Re-authenticate',
            'blocked' => 'Blocked',
            'disabled' => 'Disabled',
        ],
        'route_target' => [
            'incoming_invoice' => 'Incoming invoices',
            'document' => 'Document (DMS)',
            'b2b_order' => 'B2B order (openTRANS)',
        ],
        'item_status' => [
            'imported' => 'Imported',
            'inbox' => 'Inbox',
            'rejected' => 'Rejected',
            'duplicate' => 'Duplicate',
            'source_gone' => 'Source removed',
        ],
    ],
    'product' => [
        'status' => [
            'active' => 'Active',
            'phasing_out' => 'Phasing out',
            'discontinued' => 'Discontinued',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Active',
            'paused' => 'Paused',
            'archived' => 'Archived',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'done' => 'Done',
        ],
        'priority' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'signed' => 'Signed',
            'locked' => 'Locked',
        ],
        'kind' => [
            'project' => 'Project',
            'personal_day' => 'Personal day',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Work',
            'travel' => 'Travel',
            'standby' => 'Standby',
        ],
    ],
    'expense' => [
        'status' => [
            'draft' => 'Draft',
            'pending' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'reimbursed' => 'Reimbursed',
            'invoiced' => 'Invoiced',
        ],
        'payment_method' => [
            'private_paid' => 'Paid privately',
            'company_card' => 'Company card',
            'cash' => 'Cash box',
            'bank_transfer' => 'Bank transfer',
        ],
    ],
    'per_diem' => [
        'day_kind' => [
            'departure_day' => 'Departure day',
            'full_day' => 'Full travel day',
            'return_day' => 'Return day',
            'single_day' => 'Single-day trip',
        ],
        'trip_status' => [
            'draft' => 'Draft',
            'converted' => 'Converted to expense',
            'cancelled' => 'Cancelled',
        ],
    ],
    'notification' => [
        'event' => [
            'crisis' => [
                'alert' => 'Crisis alert',
            ],
            'claim' => [
                'escalation' => 'Claim overdue',
            ],
            'rental' => [
                'returnOverdue' => 'Rental return overdue',
            ],
            'assetFinance' => [
                'deadline' => 'Leasing deadline due',
            ],
            'contract' => [
                'deadlineDue' => 'Contract deadline due',
            ],
            'invoice' => [
                'recurringDraft' => 'Invoice draft from billing schedule',
            ],
            'fleet' => [
                'licenseCheckDue' => 'Driver licence check due',
            ],
            'recruiting' => [
                'applicationReceived' => 'Public application received',
            ],
            'assetCompliance' => [
                'inspectionDue' => 'Inspection due/overdue',
            ],
            'ticket' => [
                'assigned' => 'Ticket assigned',
                'customerReplied' => 'Customer replied',
                'waitingExpired' => 'Ticket follow-up due',
            ],
            'problem' => [
                'effectivenessDue' => 'Problem effectiveness review due',
            ],
            'openIssue' => [
                'assigned' => 'Open issue assigned',
                'dueSoon' => 'Open issue due soon',
                'overdue' => 'Open issue overdue',
            ],
            'communication' => [
                'followupDueSoon' => 'Follow-up due soon',
                'followupOverdue' => 'Follow-up overdue',
            ],
            'document' => [
                'expiringSoon' => 'Document expiring soon',
                'expired' => 'Document expired',
            ],
            'timeCorrection' => [
                'requested' => 'Time correction requested',
                'decided' => 'Time correction decided',
            ],
            'overtime' => [
                'requested' => 'Overtime request submitted',
                'decided' => 'Overtime request decided',
            ],
            'vacation' => [
                'requested' => 'Vacation request submitted',
                'decided' => 'Vacation request decided',
            ],
            'attendance' => [
                'unclearCase' => 'Unclear case (clock times)',
            ],
            'monthClosure' => [
                'submitted' => 'Month closure submitted',
                'decided' => 'Month closure decided',
            ],
            'isms' => [
                'certificateExpiring' => 'ISMS certificate expiring soon',
                'correctiveActionOverdue' => 'ISMS corrective action overdue',
                'riskReviewDue' => 'ISMS risk review due',
                'vulnerabilityOverdue' => 'ISMS vulnerability overdue',
                'incidentCritical' => 'Critical ISMS security incident',
                'supplierReviewOverdue' => 'ISMS supplier review overdue',
            ],
            'sla' => [
                'atRisk' => 'SLA deadline at risk',
                'breached' => 'SLA deadline breached',
                'quotaWarning' => 'SLA quota nearly used up',
            ],
            'asset' => [
                'returnOverdue' => 'Asset return overdue',
            ],
            'safety' => [
                'criticalEvent' => 'Critical safety event',
            ],
            'qualification' => [
                'expiring' => 'Qualification expiring soon',
            ],
            'shiftExchange' => [
                'requested' => 'Shift exchange requested',
                'decided' => 'Shift exchange decided',
            ],
            'customer' => [
                'queryRaised' => 'Customer raised a query',
            ],
            'ideaMap' => [
                'shared' => 'Idea map shared with you',
            ],
            'shipment' => [
                'deliveryProblem' => 'Delivery problem with a shipment',
            ],
            'cti' => [
                'incomingCall' => 'Incoming call',
            ],
            'maintenance' => [
                'dueSoon' => 'Maintenance/inspection due soon',
                'overdue' => 'Maintenance/inspection overdue',
            ],
            'domain' => [
                'expiring' => 'Domain expiring / renewal failed',
                'transferChanged' => 'Domain transfer status changed',
                'syncFailed' => 'Domain sync failed',
                'highRiskAction' => 'High-risk domain action approved',
            ],
            'finance' => [
                'transferFailed' => 'Billing transfer failed',
                'bankImportFailed' => 'Bank import failed',
                'reconciliationReview' => 'Payment reconciliation needs review',
            ],
            'investment' => [
                'decisionDue' => 'Investment decision due',
                'decided' => 'Investment request decided',
            ],
            'inventory' => [
                'lotExpiring' => 'Lot expiring (best-before)',
            ],
            'operations' => [
                'backupOverdue' => 'Backup overdue',
                'backupFailed' => 'Backup failed',
                'restoreTestOverdue' => 'Restore test overdue',
                'updateAvailable' => 'Update available',
                'updateSecurity' => 'Security update available',
                'licenseExpiring' => 'License expiring soon',
                'credentialExpiring' => 'Credential/token expiring soon',
                'connectionFailing' => 'Connection failing',
                'componentEol' => 'Component end-of-life (EOL)',
                'pluginDisabled' => 'Plugin disabled automatically',
                'schedulerOverdue' => 'Scheduled task overdue',
                'maintenanceScheduled' => 'Maintenance window announced',
                'problemReportReceived' => 'New problem report received',
            ],
            'security' => [
                'integrity' => 'Source code integrity',
                'threat' => 'Threat detection',
                'newDevice' => 'Sign-in from new device',
                'lockout' => 'Account temporarily locked',
            ],
        ],
        'channel' => [
            'inApp' => 'In-app',
            'mail' => 'E-mail',
            'push' => 'Push',
            'teams' => 'Microsoft Teams',
            'mattermost' => 'Mattermost',
            'calendar' => 'Calendar',
        ],
    ],

    'customer-query' => [
        'status' => [
            'open' => 'Open',
            'answered' => 'Answered',
            'closed' => 'Closed',
        ],
    ],

    'shift' => [
        'availability_kind' => [
            'available' => 'Available',
            'unavailable' => 'Unavailable',
            'preferred' => 'Preferred',
        ],
        'preference' => [
            'want' => 'Wish',
            'avoid' => 'Aversion',
            'off' => 'Day-off wish',
        ],
        'exchange_status' => [
            'requested' => 'Requested',
            'accepted' => 'Accepted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Withdrawn',
        ],
    ],

    'sla' => [
        'status' => [
            'none' => 'No SLA',
            'met' => 'SLA met',
            'onTrack' => 'SLA on track',
            'atRisk' => 'SLA at risk',
            'breached' => 'SLA breached',
        ],
        'violationKind' => [
            'responseTime' => 'Response time',
            'resolutionTime' => 'Resolution time',
        ],
        'quotaPeriod' => [
            'month' => 'Month',
            'quarter' => 'Quarter',
            'year' => 'Year',
        ],
    ],

    'safety' => [
        'kind' => [
            'accident' => 'Accident',
            'nearMiss' => 'Near miss',
            'hazard' => 'Hazard',
            'defect' => 'Defect',
        ],
        'severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'status' => [
            'reported' => 'Reported',
            'investigating' => 'Investigating',
            'measuresDefined' => 'Measures defined',
            'closed' => 'Closed',
        ],
    ],

    'open-issue' => [
        'status' => [
            'open' => 'Open',
            'inProgress' => 'In progress',
            'blocked' => 'Blocked',
            'done' => 'Done',
            'wontDo' => 'Won\'t do',
            'reopened' => 'Reopened',
        ],
        'severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'source' => [
            'manual' => 'Manual',
            'protocolDefect' => 'From protocol',
            'communicationFollowup' => 'From communication',
            'procedureDeviation' => 'From procedure deviation',
            'customerRejection' => 'Customer rejection',
        ],
        'visibility' => [
            'internal' => 'Internal',
            'customer' => 'Customer-visible',
        ],
    ],
    'communication' => [
        'type' => [
            'call' => 'Phone call',
            'email' => 'Email',
            'meeting' => 'On-site meeting',
            'videocall' => 'Video call',
            'chat' => 'Chat / messenger',
            'internal' => 'Internal consultation',
            'decision' => 'Decision',
            'letter' => 'Letter / fax',
            'other' => 'Other',
        ],
        'direction' => [
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
            'internal' => 'Internal',
        ],
        'visibility' => [
            'internal' => 'Internal',
            'customer' => 'Customer-visible',
        ],
        'party' => [
            'internal' => 'Internal',
            'customer' => 'Customer',
            'thirdParty' => 'Third party',
        ],
    ],
    'knowledge' => [
        'status' => [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ],
        'visibility' => [
            'internal' => 'Internal (whole organisation)',
            'team' => 'Team-scoped',
        ],
    ],
    'form' => [
        'template_status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'archived' => 'Archived',
        ],
        'field_type' => [
            'text' => 'Text',
            'textarea' => 'Multi-line text',
            'number' => 'Number',
            'date' => 'Date',
            'select' => 'Select',
            'checkbox' => 'Checkbox',
            'photo' => 'Photo',
            'file' => 'File',
            'signature' => 'Signature',
        ],
    ],
    'document' => [
        'type' => [
            'contract' => 'Contract',
            'testReport' => 'Test report',
            'certificate' => 'Certificate',
            'manual' => 'Manual',
            'datasheet' => 'Datasheet',
            'manufacturerDoc' => 'Manufacturer document',
            'permit' => 'Permit',
            'insurance' => 'Insurance',
            'invoice' => 'Invoice',
            'other' => 'Other',
        ],
        'status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'expired' => 'Expired',
            'archived' => 'Archived',
        ],
    ],
    'protocol' => [
        'status' => [
            'draft' => 'Draft',
            'in_review' => 'In review',
            'signed' => 'Signed',
            'archived' => 'Archived',
            'superseded' => 'Superseded',
        ],
        'type' => [
            'acceptance' => 'Acceptance',
            'service' => 'Service call',
            'maintenance' => 'Maintenance',
            'handover' => 'Handover',
            'defect' => 'Defect report',
            'inspection' => 'Inspection',
            'siteVisit' => 'Site visit',
            'other' => 'Other',
        ],
        'visibility' => [
            'internal' => 'Internal',
            'customer' => 'Customer-visible',
        ],
        'item-result' => [
            'ok' => 'OK',
            'notok' => 'Not OK',
            'n_a' => 'Not applicable',
            'open' => 'Open',
        ],
        'signature-role' => [
            'customer' => 'Customer',
            'contractor' => 'Contractor',
            'witness' => 'Witness',
        ],
        'signature-method' => [
            'onscreen' => 'On-screen signature',
            'portal' => 'Customer portal',
            'emailLink' => 'Email link',
            'paper' => 'Paper',
        ],
        'item-type' => [
            'group' => 'Section',
            'text' => 'Free text',
            'boolean' => 'Yes/no item',
            'choice' => 'Single choice',
            'multichoice' => 'Multiple choice',
            'number' => 'Measurement / number',
            'range' => 'Target range',
            'date' => 'Date',
            'datetime' => 'Date & time',
            'signature' => 'Signature',
            'photo' => 'Mandatory photo',
            'file' => 'Mandatory document',
            'defect' => 'Defect',
            'measurement.timestamped' => 'Measurement series',
            'procedure_step' => 'Procedure step',
            'signoff_internal' => 'Internal approval',
        ],
        'item-photo-phase' => [
            'before' => 'Before',
            'after' => 'After',
            'detail' => 'Detail',
            'defect' => 'Defect',
            'reference' => 'Reference',
        ],
    ],
    'procedure' => [
        'risk-level' => [
            'low' => 'Low',
            'normal' => 'Normal',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'step-type' => [
            'confirm' => 'Confirmation',
            'text' => 'Text',
            'number' => 'Number/measurement',
            'choice' => 'Choice',
            'photo' => 'Photo',
            'file' => 'File',
            'backup' => 'Backup record',
            'signature' => 'Signature',
            'material' => 'Material entry',
            'dienstmittel' => 'Service equipment',
            'freigabe' => 'Approval (four-eyes)',
            'messreihe' => 'Measurement series',
            'link_protocol' => 'Link protocol',
            'link_test' => 'Link test',
            'wait' => 'Wait time',
        ],
        'proof-type' => [
            'backup' => 'Backup',
            'file' => 'File',
            'photo' => 'Photo',
            'measure' => 'Measurement',
            'signature' => 'Signature',
        ],
        'run-status' => [
            'open' => 'Open',
            'inProgress' => 'In progress',
            'blocked' => 'Blocked',
            'completed' => 'Completed',
            'aborted' => 'Aborted',
        ],
        'step-run-status' => [
            'pending' => 'Pending',
            'done' => 'Done',
            'n_a' => 'Not applicable',
            'failed' => 'Failed',
            'deviated' => 'Deviation',
            'blocked' => 'Blocked',
        ],
        'backup-scope' => [
            'config' => 'Configuration',
            'database' => 'Database',
            'fullSystem' => 'Full system',
            'customScript' => 'Custom script',
        ],
        'backup-storage-target' => [
            'attachment' => 'Attachment',
            'external' => 'External storage',
        ],
        'backup-verify-method' => [
            'checksum' => 'Checksum comparison',
            'restoreCheck' => 'Restore test',
            'managerConfirmation' => 'Management confirmation',
        ],
        'deviation-type' => [
            'not_applicable' => 'Not applicable',
            'not_possible' => 'Not possible',
            'partial' => 'Partially fulfilled',
            'alternative_method' => 'Alternative method',
            'failed_check' => 'Reading out of tolerance',
            'material_substitute' => 'Material substitute',
            'safety_block' => 'Safety abort',
            'customer_decline' => 'Customer declined',
        ],
        'deviation-severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'deviation-proposed-action' => [
            'none' => 'No follow-up action',
            'open_issue' => 'Open issue',
            'new_diary_entry' => 'New job',
            'requalify' => 'Run again',
            'escalate' => 'Escalation',
        ],
    ],
    'duty_plan' => [
        'status' => [
            'draft' => 'Draft',
            'published' => 'Published',
        ],
    ],
    'export' => [
        'entity' => [
            'customers' => 'Customers',
            'projects' => 'Projects',
            'users' => 'Users',
            'materials' => 'Materials',
            'scheduled_shifts' => 'Scheduled shifts',
            'tours' => 'Tours',
        ],
        'format' => [
            'csv' => 'CSV',
            'xlsx' => 'XLSX',
        ],
        'state' => [
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'failed' => 'Failed',
        ],
    ],
    'compliance' => [
        'finding-status' => [
            'open' => 'Open',
            'acknowledged' => 'Acknowledged',
            'resolved' => 'Resolved',
            'accepted' => 'Accepted',
        ],
    ],
    'isms' => [
        'security-incident-category' => [
            'malware' => 'Malware',
            'phishing' => 'Phishing',
            'dataLoss' => 'Data loss',
            'unauthorizedAccess' => 'Unauthorized access',
            'serviceOutage' => 'Service outage',
            'misconfiguration' => 'Misconfiguration',
            'physical' => 'Physical incident',
            'other' => 'Other',
        ],
        'security-incident-status' => [
            'reported' => 'Reported',
            'triage' => 'Triage',
            'contained' => 'Contained',
            'eradicated' => 'Eradicated',
            'recovered' => 'Recovered',
            'closed' => 'Closed',
        ],
        'incident-severity' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ],
        'vulnerability-status' => [
            'open' => 'Open',
            'underReview' => 'Under review',
            'mitigating' => 'Mitigating',
            'resolved' => 'Resolved',
            'accepted' => 'Accepted',
            'notAffected' => 'Not affected',
        ],
        'exploitability' => [
            'unknown' => 'Unknown',
            'underInvestigation' => 'Under investigation',
            'exploitable' => 'Exploitable',
            'notExploitable' => 'Not exploitable',
        ],
        'vulnerability-source' => [
            'manual' => 'Manual',
            'advisoryImport' => 'Advisory import',
        ],
        'supplier-assessment-status' => [
            'draft' => 'Draft',
            'assessed' => 'Assessed',
            'approved' => 'Approved',
            'flagged' => 'Flagged',
        ],
        'advisory-format' => [
            'csaf' => 'CSAF',
            'vex' => 'VEX',
        ],
        'audit-package-status' => [
            'draft' => 'Draft',
            'finalized' => 'Finalised',
        ],
        'audit-kind' => [
            'internal' => 'Internal',
            'external' => 'External',
            'supplier' => 'Supplier',
        ],
        'audit-status' => [
            'planned' => 'Planned',
            'inPreparation' => 'In preparation',
            'inProgress' => 'In progress',
            'reportIssued' => 'Report issued',
            'closed' => 'Closed',
        ],
        'finding-kind' => [
            'nonconformityMajor' => 'Major nonconformity',
            'nonconformityMinor' => 'Minor nonconformity',
            'observation' => 'Observation',
            'improvement' => 'Improvement opportunity',
        ],
        'finding-status' => [
            'open' => 'Open',
            'inCorrection' => 'In correction',
            'effectivenessCheck' => 'Effectiveness check',
            'closed' => 'Closed',
        ],
        'corrective-action-status' => [
            'open' => 'Open',
            'inProgress' => 'In progress',
            'done' => 'Implemented',
            'effective' => 'Effective',
            'ineffective' => 'Ineffective',
        ],
        'review-status' => [
            'draft' => 'Draft',
            'approved' => 'Approved',
        ],
        'assessment-kind' => [
            'gross' => 'Gross',
            'net' => 'Net',
            'target' => 'Target',
        ],
        'assessment-status' => [
            'draft' => 'Draft',
            'approved' => 'Approved',
        ],
        'risk-category' => [
            'organizational' => 'Organisational',
            'technical' => 'Technical',
            'physical' => 'Physical',
            'personnel' => 'Personnel',
            'supplier' => 'Supplier',
        ],
        'risk-treatment' => [
            'avoid' => 'Avoid',
            'mitigate' => 'Mitigate',
            'transfer' => 'Transfer',
            'accept' => 'Accept',
        ],
        'risk-status' => [
            'identified' => 'Identified',
            'analyzed' => 'Analysed',
            'treated' => 'Treated',
            'accepted' => 'Accepted',
            'closed' => 'Closed',
        ],
        'requirement-source' => [
            'catalog' => 'Reference catalogue',
            'custom' => 'Custom requirement',
        ],
        'control-implementation-status' => [
            'open' => 'Open',
            'partial' => 'Partially implemented',
            'implemented' => 'Implemented',
            'notApplicable' => 'Not applicable',
        ],
        'software-category' => [
            'os' => 'Operating system',
            'application' => 'Application',
            'service' => 'Service',
            'library' => 'Library',
            'other' => 'Other',
        ],
        'support-status' => [
            'supported' => 'Supported',
            'extendedSupport' => 'Extended support',
            'endOfLife' => 'End of life',
            'unknown' => 'Unknown',
        ],
        'norm-conformity-status' => [
            'notAssessed' => 'Not assessed',
            'gapAnalysisDone' => 'Gap analysis done',
            'inProgress' => 'In progress',
            'internallyAuditReady' => 'Internally audit-ready',
            'externalAuditPlanned' => 'External audit planned',
            'certified' => 'Certified',
            'certificateSuspended' => 'Certificate suspended',
            'certificateExpired' => 'Certificate expired',
        ],
    ],
    'surcharge' => [
        'kind' => [
            'night' => 'Night',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
            'holiday' => 'Public holiday',
            'custom' => 'Custom',
            'oncall' => 'On-call duty',
            'standby' => 'Standby duty',
            'overtime' => 'Overtime',
        ],
    ],
    // Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098).
    'billing' => [
        // Belegfluss (Feature 105, MVP-542)
        'direction' => [
            'outgoing' => 'Outgoing',
            'incoming' => 'Incoming',
            'neutral' => 'No monetary effect',
        ],
        'kind' => [
            'quote' => 'Quote',
            'order_confirmation' => 'Order confirmation',
            'delivery_note' => 'Delivery note',
            'invoice' => 'Invoice',
            'down_payment' => 'Down payment invoice',
            'down_payment_deduction' => 'Down payment deduction',
            'credit_note' => 'Credit note',
            'cancellation' => 'Cancellation',
            'expense' => 'Expense',
            'other' => 'Other document',
        ],
        'origin' => [
            'local' => 'Local',
            'lexoffice' => 'Lexoffice',
        ],
        'agreement-mode' => [
            'account' => 'Customer account (no invoices)',
            'invoice' => 'Monthly invoice',
            'retainer' => 'Retainer (Lexoffice)',
        ],
        'rate-day-type' => [
            'weekday' => 'Weekday',
            'weekend' => 'Weekend',
        ],
        'account-payment-source' => [
            'manual' => 'Manual',
            'bank' => 'Bank',
            'import' => 'Import',
            'lexoffice' => 'Lexoffice',
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice leads',
            'datev' => 'DATEV leads',
            'orgamax' => 'orgaMAX leads',
            'sevdesk' => 'sevDesk leads',
            'easybill' => 'easybill leads',
        ],
        'transfer-channel' => [
            'time' => 'Services/time',
            'material' => 'Products/material',
        ],
        'transfer-target' => [
            'lexoffice' => 'Lexoffice',
            'datev' => 'DATEV',
            'orgamax' => 'orgaMAX (order)',
            'sevdesk' => 'sevDesk (invoice draft)',
            'easybill' => 'easybill (invoice draft)',
            'file' => 'File export',
        ],
        'transfer-status' => [
            'draft' => 'Draft',
            'confirmed' => 'Confirmed',
            'transferred' => 'Transferred',
            'failed' => 'Failed',
            'voided' => 'Voided',
            'cancelled' => 'Cancelled',
        ],
        'chart-of-accounts' => [
            'skr03' => 'SKR03',
            'skr04' => 'SKR04',
        ],
        'datev-batch-status' => [
            'draft' => 'Draft',
            'exported' => 'Exported',
        ],
        // Payment reconciliation (Feature 045, priority 3).
        'bank-statement-format' => [
            'camt053' => 'CAMT.053',
            'mt940' => 'MT940',
            'ofx' => 'OFX',
            'qif' => 'QIF',
            'qxf' => 'QXF',
            'pain001' => 'PAIN.001',
            'pain008' => 'PAIN.008',
        ],
        'transaction-direction' => [
            'credit' => 'Incoming',
            'debit' => 'Outgoing',
        ],
        'balance-check' => [
            'ok' => 'Balance chain consistent',
            'mismatch' => 'Balance mismatch',
            'unknown' => 'Balances incomplete',
        ],
        'match-status' => [
            'unmatched' => 'Open',
            'suggested' => 'Suggestions',
            'matched' => 'Allocated',
            'ignored' => 'Set aside',
            'unassignable' => 'Unassignable',
            'duplicate' => 'Duplicate',
        ],
        'allocation-kind' => [
            'payment' => 'Payment',
            'partial' => 'Partial payment',
            'overpayment' => 'Overpayment',
            'reimbursement' => 'Reimbursement',
            'chargeback' => 'Chargeback',
            'skonto' => 'Cash discount (revenue reduction)',
        ],
    ],

    // Daily close (MVP-015, docs/tagesabschluss.md §3/§5).
    'dayClosure' => [
        'status' => [
            'open' => 'Open',
            'closed' => 'Closed',
            'correction' => 'In correction',
            'locked' => 'Locked',
        ],
    ],
    'dayCorrection' => [
        'status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ],
    ],

    // Restore test result (Feature 017).
    'backup' => [
        // Cloud backup targets (feature 017, phase 32).
        'provider' => [
            'dropbox' => 'Dropbox',
            'microsoft' => 'Microsoft OneDrive/SharePoint',
            'google' => 'Google Drive',
            'nextcloud' => 'Nextcloud',
        ],
        'target_status' => [
            'draft' => 'Draft',
            'active' => 'Active',
            'reauth_required' => 'Re-authentication required',
            'blocked' => 'Blocked',
            'disabled' => 'Disabled',
        ],
        'generation_status' => [
            'building' => 'Building',
            'uploading' => 'Uploading',
            'committed' => 'Committed',
            'verified' => 'Verified',
            'verify_failed' => 'Verification failed',
            'failed' => 'Failed',
        ],
        'retention_class' => [
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ],
        'restore-test-result' => [
            'passed' => 'Passed',
            'partial' => 'With conditions',
            'failed' => 'Failed',
        ],
    ],

    // Maintenance plan due action (Feature 010 → Rank 43).
    'maintenance' => [
        'due_action' => [
            'none' => 'Notice only (no record)',
            'ticket' => 'Create a service ticket',
        ],
    ],

    'security' => [
        'integrity_check_status' => [
            'baseline' => 'Baseline created',
            'ok' => 'OK',
            'deviation' => 'Deviation',
            'missing_baseline' => 'No baseline',
            'error' => 'Error',
        ],
    ],

    'passenger' => [
        'operation_mode' => [
            'taxi' => 'Taxi service (§ 47 PBefG)',
            'rental_car' => 'Rental car service (§ 49 PBefG)',
            'pooled_on_demand' => 'Pooled on-demand service (§ 50 PBefG)',
        ],
        'ride_status' => [
            'requested' => 'Requested',
            'accepted' => 'Accepted',
            'assigned' => 'Assigned',
            'en_route_pickup' => 'En route to pickup',
            'waiting' => 'Waiting',
            'occupied' => 'Occupied',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'Passenger no-show',
            'aborted' => 'Aborted',
        ],
        'price_kind' => [
            'tariff' => 'Tariff',
            'fixed_price' => 'Fixed price',
            'contract' => 'Contract price',
        ],
        'order_channel' => [
            'hail' => 'Street hail / rank',
            'phone' => 'Phone',
            'app' => 'App',
            'web' => 'Web',
            'mediator' => 'Dispatch center',
            'contract' => 'Framework contract',
        ],
    ],
    'print' => [
        'order_status' => [
            'data_check' => 'Data check',
            'approved' => 'Approved',
            'in_production' => 'In production',
            'quality_check' => 'Quality check',
            'rework' => 'Rework',
            'ready' => 'Ready for hand-over',
            'issued' => 'Handed over',
            'cancelled' => 'Cancelled',
        ],
        'preflight_status' => [
            'pending' => 'Pending',
            'passed' => 'Passed',
            'warnings' => 'With warnings',
            'failed' => 'Failed',
            'overridden' => 'Overridden with reason',
        ],
        'output_kind' => [
            'pickup' => 'Pickup',
            'shipping' => 'Shipping',
            'counter' => 'Counter sale',
        ],
    ],
];
