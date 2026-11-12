<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : crisis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Offline-Krisenmappe (MVP-914).
    'offline' => [
        'save' => 'Save crisis pack offline',
        'hint' => 'Stores the active crises with situation, actions and crisis team contacts on this device; readable on the offline page without a connection. Deleted on sign-out.',
    ],
    // Öffentliche Statusseite (MVP-915).
    'status_page' => [
        'title' => 'Status page (public)',
        'intro' => 'The status page shows sent crisis messages addressed to the public without signing in; messages to customers also appear in the customer portal. Only subject, text and time are shown, never the crisis file.',
        'token_once' => 'This link is shown only now — it is stored nowhere.',
        'state' => 'Status',
        'state_none' => 'Not set up',
        'state_active' => 'Publicly reachable',
        'state_paused' => 'Paused',
        'hint' => 'Identifier',
        'issued_at' => 'Issued on',
        'action' => [
            'issue' => 'Issue link',
            'rotate' => 'Renew link',
            'revoke' => 'Revoke access',
            'pause' => 'Pause',
            'resume' => 'Activate',
        ],
        'confirm' => [
            'rotate' => 'Issue a new link? The previous link will stop working.',
            'revoke' => 'Revoke access? The status page will no longer be publicly reachable.',
        ],
        'flash' => [
            'issued' => 'New link issued.',
            'revoked' => 'Access revoked.',
            'saved' => 'Saved.',
        ],
        'public_title' => 'Current status – :org',
        'public_intro' => 'Here we inform you about ongoing disruptions and incidents.',
        'all_clear' => 'There are currently no disruptions.',
        'resolved' => 'all clear',
    ],
    // BIA-Register (MVP-943).
    'bia' => [
        'title' => 'BIA register',
        'subtitle' => 'Business processes with criticality, recovery objectives (RTO/RPO) and maximum tolerable period of disruption (MTPD).',
        'create' => 'Add process',
        'edit' => 'Edit process',
        'save' => 'Save',
        'empty' => 'No processes in the register yet.',
        'inactive' => 'inactive',
        'import' => 'Import from registers',
        'import_hint' => 'Suggestions from the record of processing activities, ISMS risks and active procedure templates. Only what you select is imported.',
        'import_submit' => 'Import selection',
        'adopt' => 'Adopt from BIA register',
        'kind' => [
            'processing_activity' => 'Processing activity',
            'isms_risk' => 'ISMS risk',
            'procedure_template' => 'Procedure template',
        ],
        'criticality' => [
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'critical' => 'critical',
        ],
        'field' => [
            'name' => 'Process',
            'description' => 'Description',
            'criticality' => 'Criticality',
            'rto_hours' => 'RTO (hours)',
            'rpo_hours' => 'RPO (hours)',
            'mtpd_hours' => 'MTPD (hours)',
            'owner' => 'Owner',
            'dependencies' => 'Dependencies (systems, suppliers, people)',
            'review_due_on' => 'Review due',
            'is_active' => 'Active',
        ],
        'flash' => [
            'saved' => 'Process saved.',
            'imported' => ':count processes imported.',
            'adopted' => 'Process adopted from the BIA register.',
        ],
    ],
    // BCM-Auswertung (MVP-944).
    'bcm_report' => [
        'title' => 'BCM report',
        'subtitle' => 'Indicators according to ISO 22301: exercises, actions, reviews and BIA status.',
        'back' => 'Crisis management',
        'disclaimer' => 'Indicators from the recorded data; no statement about certifiability.',
        'overdue' => ':count overdue',
        'row' => [
            'exercises' => 'Exercises since :date',
            'effectiveness' => 'Effectiveness',
            'exercises_due' => 'Exercises due',
            'actions_open' => 'Open actions',
            'reviews' => 'Ended crises with review',
            'processes' => 'Processes in the BIA register',
            'without_rto' => 'Processes without RTO',
            'review_due' => 'Processes with review due',
        ],
        'effectiveness' => [
            'effective' => 'effective',
            'partly' => 'partly',
            'ineffective' => 'ineffective',
            'open' => 'not rated',
        ],
    ],
    // Krisenraum (MVP-963).
    'room' => [
        'title' => 'Crisis room',
        'present' => 'Present',
        'nobody' => 'nobody else',
        'no_markers' => 'No locations: link assets or customers with coordinates or add map points.',
        'add_point' => 'Add map point',
        'field' => [
            'label' => 'Label',
            'kind' => 'Type',
            'lat' => 'Latitude',
            'lng' => 'Longitude',
        ],
        'kind' => [
            'incident' => 'Incident site',
            'assembly' => 'Assembly point',
            'closure' => 'Closure',
            'resource' => 'Resource',
            'other' => 'Other',
        ],
        'layer' => [
            'linked' => 'Linked objects',
        ],
        'link' => [
            'asset' => 'Asset',
            'customer' => 'Customer',
        ],
        'flash' => [
            'point_saved' => 'Map point added.',
            'point_deleted' => 'Map point removed.',
        ],
    ],
    // Meldefristen je Organisation (MVP-1589).
    'deadlines' => [
        'title' => 'Reporting deadlines',
        'subtitle' => 'Deadlines per crisis category. A category’s own deadlines replace its defaults; the dates of a crisis file run from activation.',
        'create' => 'Add reporting deadline',
        'edit' => 'Edit reporting deadline',
        'save' => 'Save',
        'defaults' => 'Defaults',
        'empty' => 'No own deadlines yet. The defaults apply.',
        'no_defaults' => 'No defaults stored.',
        'inactive' => 'inactive',
        'replaced' => 'replaced',
        'immediate' => 'immediately',
        'adopt' => 'Adopt as own deadlines',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'form_hint' => 'As soon as a category has an active own deadline, only the own deadlines apply to it. Adopt the defaults first if you only want to change one deadline.',
        'field' => [
            'category' => 'Category',
            'label' => 'Label',
            'offset_hours' => 'Deadline (hours)',
            'offset_hours_hint' => 'Hours from activation of the crisis. Empty = immediately.',
            'source' => 'Legal basis',
            'active' => 'Active',
        ],
        'flash' => [
            'saved' => 'Reporting deadline saved.',
            'adopted' => 'Defaults adopted as own deadlines.',
            'already_own' => 'This category already has own deadlines.',
            'activated' => 'Reporting deadline activated.',
            'deactivated' => 'Reporting deadline deactivated.',
        ],
    ],
    // Abhängigkeiten, Ersatzprozess, Anwesenheitsliste, Eröffnen aus Vorfall (MVP-1605).
    'link' => [
        'dialog_title' => 'Link a record',
        'target' => 'Record',
        'target_hint' => 'For each type, the most recent records you may read are offered.',
        'note' => 'Note on the link',
        'none_available' => 'There are no records you can link.',
        'opened_from' => 'Crisis opened from this incident',
        'type' => [
            'service_ticket' => 'Service ticket',
            'isms_incident' => 'Security incident',
            'privacy_incident' => 'Data protection incident',
            'safety_event' => 'Occupational safety event',
            'procedure_run' => 'Playbook/procedure run',
            'document' => 'Document',
            'asset' => 'Asset',
            'customer' => 'Customer',
        ],
    ],
    'open' => [
        'action' => 'Open crisis',
        'from' => 'The crisis is opened from :source and linked to the incident.',
    ],
    'action' => [
        'depends_on' => 'Predecessor',
        'no_predecessor' => 'no predecessor',
        'after' => 'after: :title',
        'blocked_by' => 'The action can only be completed once “:title” is done or cancelled.',
        'depends_foreign' => 'The predecessor does not belong to this crisis file.',
        'depends_cycle' => 'The predecessor itself waits for this measure — that would create a cycle.',
        'predecessor_saved' => 'Predecessor saved.',
    ],
    'bcm' => [
        'substitute_process' => 'Substitute process',
        'residual_note' => 'Remaining limitation',
    ],
    'presence' => [
        'open' => 'Emergency attendance list',
    ],
];
