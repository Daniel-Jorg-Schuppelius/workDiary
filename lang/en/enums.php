<?php

return [
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
            'monthClosure' => [
                'submitted' => 'Month closure submitted',
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
        ],
    ],
    'finance' => [
        'billing-mode' => [
            'workdiary' => 'WorkDiary (local)',
            'lexoffice' => 'Lexoffice leads',
            'datev' => 'DATEV leads',
            'orgamax' => 'orgaMAX leads',
            'sevdesk' => 'sevDesk leads',
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
            'file' => 'File export',
        ],
        'transfer-status' => [
            'draft' => 'Draft',
            'confirmed' => 'Confirmed',
            'transferred' => 'Transferred',
            'failed' => 'Failed',
            'voided' => 'Voided',
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
];
