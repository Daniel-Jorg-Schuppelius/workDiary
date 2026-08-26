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

    // Occupational safety register (Feature 132): risk assessment, instruction, medical checkup.
    'register' => [
        'section' => 'Occupational Safety',
        'nav' => [
            'assessments' => 'Risk assessments',
            'instructions' => 'Safety instructions',
            'checkups' => 'Medical checkups',
        ],
        'title' => [
            'assessments' => 'Risk assessments',
            'instructions' => 'Safety instructions',
            'checkups' => 'Occupational medical checkups',
        ],
        'subtitle' => [
            'assessments' => 'Risk assessments under § 5 ArbSchG — versioned, with review date.',
            'instructions' => 'Safety instructions under DGUV Regulation 1 § 4 with proof of participation per person.',
            'checkups' => 'Occupational medical checkups under ArbMedVV — only kind, date and certificate, no health data.',
        ],
        'field' => [
            'assessment_no' => 'Number',
            'version' => 'Version',
            'area' => 'Area',
            'activity' => 'Activity',
            'description' => 'Description',
            'status' => 'Status',
            'review_due_on' => 'Review due',
            'approved_by' => 'Approved by',
            'approved_at' => 'Approved on',
            'created_by' => 'Created by',
            'supersedes' => 'Supersedes',
            'superseded_by' => 'Superseded by',
            'items' => 'Hazards',
            'position' => 'Pos.',
            'hazard' => 'Hazard',
            'measure' => 'Measure',
            'severity' => 'Severity (S)',
            'likelihood' => 'Likelihood (L)',
            'risk_before' => 'Risk before',
            'risk_after' => 'Risk after',
            'before' => 'Before measure',
            'after' => 'After measure',
            'instruction_no' => 'Number',
            'topic' => 'Topic',
            'held_on' => 'Date',
            'instructor' => 'Instructor',
            'assessment' => 'Risk assessment',
            'repeat_interval_months' => 'Repeat (months)',
            'notes' => 'Notes',
            'participants' => 'Participants',
            'signed' => 'Confirmed',
            'signed_at' => 'Confirmed on',
            'method' => 'Proof method',
            'next_due_on' => 'Next due',
            'user' => 'Person',
            'kind' => 'Kind',
            'occasion' => 'Occasion',
            'performed_on' => 'Performed on',
            'certificate_on_file' => 'Certificate on file',
        ],
        'action' => [
            'create_assessment' => 'Create risk assessment',
            'edit' => 'Edit',
            'save' => 'Save',
            'delete' => 'Delete',
            'show' => 'View',
            'back' => 'Back',
            'transition' => 'Change status',
            'new_version' => 'Create follow-up version',
            'add_item' => 'Add hazard',
            'edit_item' => 'Edit hazard',
            'create_instruction' => 'Record instruction',
            'sign' => 'Confirm participation',
            'create_checkup' => 'Record checkup',
        ],
        'filter' => [
            'all' => 'All',
            'current_only' => 'Current versions only',
            'open_only' => 'Only with open confirmations',
            'due_only' => 'Due only',
        ],
        'kpi' => [
            'review_due' => 'Review due',
            'instruction_due' => 'Repeat due',
            'checkup_due' => 'Checkup due',
        ],
        'empty' => [
            'assessments' => 'No risk assessment yet.',
            'items' => 'No hazard recorded yet.',
            'instructions' => 'No instruction recorded yet.',
            'participants' => 'No participants.',
            'checkups' => 'No checkup recorded yet.',
        ],
        'hint' => [
            'frozen' => 'This version is approved and frozen. Changes are made via a follow-up version.',
            'approve_requires_items' => 'Approval requires at least one hazard.',
            'sign_self' => 'Confirm your participation — name, time and IP address are recorded as proof.',
            'no_health_data' => 'No findings or diagnoses are stored — only kind, date and whether the certificate is on file.',
            'after_optional' => 'Risk after measure is optional — enter both values together.',
            'pdf_not_in_mvp' => 'PDF proof follows in a later stage.',
        ],
        'confirm' => [
            'delete_assessment' => 'Delete risk assessment?',
            'delete_item' => 'Delete hazard?',
            'delete_instruction' => 'Delete instruction?',
            'delete_checkup' => 'Delete checkup entry?',
            'sign' => 'Confirm participation now (binding)?',
        ],
        'flash' => [
            'assessment_created' => 'Risk assessment created.',
            'assessment_updated' => 'Risk assessment updated.',
            'assessment_transitioned' => 'Status changed.',
            'assessment_version_created' => 'Follow-up version :version created.',
            'assessment_deleted' => 'Risk assessment deleted.',
            'item_created' => 'Hazard added.',
            'item_updated' => 'Hazard updated.',
            'item_deleted' => 'Hazard removed.',
            'instruction_created' => 'Instruction recorded.',
            'instruction_updated' => 'Instruction updated.',
            'instruction_deleted' => 'Instruction deleted.',
            'participation_signed' => 'Participation confirmed.',
            'checkup_created' => 'Checkup recorded.',
            'checkup_updated' => 'Checkup updated.',
            'checkup_deleted' => 'Checkup entry deleted.',
        ],
        'error' => [
            'assessment_frozen' => 'Approved risk assessments are frozen — please create a follow-up version.',
            'approve_requires_items' => 'Approval requires at least one hazard.',
            'new_version_requires_approved' => 'A follow-up version is only possible from an approved version.',
            'after_pair_incomplete' => 'Risk after measure: enter severity and likelihood together.',
            'sign_only_self' => 'Only the listed person can confirm their participation.',
            'already_signed' => 'Participation is already confirmed.',
            'delete_with_signatures' => 'Instructions with confirmed proofs cannot be deleted.',
        ],
        'status_summary' => ':signed of :total confirmed',
    ],
];
