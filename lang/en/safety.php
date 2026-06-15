<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Safety Events',
    ],
    'subtitle' => [
        'index' => 'Record and track accidents, near misses, hazards and defects.',
    ],
    'empty' => 'No safety events recorded yet.',

    'field' => [
        'event_no' => 'No.',
        'kind' => 'Kind',
        'severity' => 'Severity',
        'status' => 'Status',
        'occurred_at' => 'Occurred at',
        'location' => 'Location',
        'affected_person' => 'Affected person',
        'reporter' => 'Reported by',
        'subject' => 'Linked to',
        'description' => 'Description',
        'immediate_action' => 'Immediate action',
        'root_cause' => 'Root cause analysis',
        'closed_at' => 'Closed at',
        'closed_by' => 'Closed by',
        'followup_title' => 'Follow-up title',
        'followup_description' => 'Description (optional)',
    ],

    'section' => [
        'status' => 'Change status',
        'followup' => 'Follow-up measure',
        'attachments' => 'Attachments',
        'followups' => 'Follow-up measures',
    ],

    'no_attachments' => 'No attachments.',
    'no_followups' => 'No follow-up measures yet.',

    'action' => [
        'create' => 'Report event',
        'edit' => 'Edit',
        'save' => 'Save',
        'show' => 'View',
        'back' => 'Back',
        'create_followup' => 'Create follow-up',
    ],

    'transition' => [
        'investigating' => 'Start investigation',
        'measuresDefined' => 'Measures defined',
        'closed' => 'Close',
    ],

    'hint' => [
        'root_cause_for_close' => 'A root cause analysis is required to close the event.',
        'followup' => 'Creates an open issue as rework linked to this event.',
    ],

    'flash' => [
        'created' => 'Safety event recorded.',
        'updated' => 'Safety event updated.',
        'deleted' => 'Safety event deleted.',
        'followup_created' => 'Follow-up measure created.',
        'status' => [
            'reported' => 'Event reset.',
            'investigating' => 'Investigation started.',
            'measuresDefined' => 'Measures defined.',
            'closed' => 'Event closed.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Invalid status change: :from → :to.',
        'close_requires_root_cause' => 'Closing requires a root cause analysis.',
    ],

    'report' => [
        'title' => 'Safety Analysis',
        'nav' => 'Occupational Safety',
        'subtitle' => 'Safety events by kind and severity over the period.',
        'by_kind' => 'By kind',
        'by_severity' => 'By severity',
        'kpi' => [
            'total' => 'Total events',
            'open' => 'Open',
            'closed' => 'Closed',
            'critical' => 'Critical',
        ],
    ],
];
