<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : communication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Communication',
        'followups' => 'Open follow-up actions',
        'notes' => 'Notes',
        'note' => 'Note',
    ],

    'field' => [
        'type' => 'Type',
        'direction' => 'Direction',
        'occurred_at' => 'Date & time',
        'subject' => 'Subject',
        'body' => 'Content / transcript',
        'result' => 'Result / agreement',
        'next_action' => 'Follow-up action',
        'next_action_due_at' => 'Due date',
        'next_action_user' => 'Responsible',
        'visibility' => 'Visibility',
        'confidential' => 'Confidential',
        'customer_visible' => 'Visible to customer',
        'participants' => 'Participants',
        'participant_name' => 'Name',
        'participant_role' => 'Role',
        'participant_party' => 'Party',
        'creator' => 'Recorded by',
        'storage' => 'Filed under',
        'customer' => 'Customer',
        'actions' => 'Actions',
        'direction_choose' => 'Please select',
    ],

    'action' => [
        'create' => 'Add note',
        'edit' => 'Edit',
        'save' => 'Save',
        'delete' => 'Delete',
        'publish' => 'Publish to customer',
        'mark_confidential' => 'Mark confidential',
        'unmark_confidential' => 'Remove confidentiality',
        'complete_followup' => 'Follow-up done',
        'add_participant' => 'Add participant',
        'remove_participant' => 'Remove participant',
        'show' => 'View',
    ],

    'flash' => [
        'created' => 'Communication note has been recorded.',
        'updated' => 'Communication note has been updated.',
        'deleted' => 'Communication note has been deleted.',
        'published' => 'Note has been published to the customer.',
        'confidential_set' => 'Note has been marked as confidential.',
        'confidential_unset' => 'Confidentiality has been removed.',
        'followup_completed' => 'Follow-up action has been marked as done.',
    ],

    'error' => [
        'internal_type_requires_internal_direction' => 'Internal consultations must use the "Internal" direction.',
        'internal_direction_requires_internal_visibility' => 'Internal communication cannot be visible to customers.',
        'confidential_requires_internal_visibility' => 'Confidential notes must remain internal.',
        'occurred_at_in_future' => 'The date must not be in the future.',
        'due_before_occurrence' => 'The follow-up due date must be after the communication date.',
        'unknown_type' => 'Unknown communication type.',
        'unknown_direction' => 'Unknown direction.',
        'confidential_not_publishable' => 'Confidential notes cannot be published to customers.',
        'internal_not_publishable' => 'Internal communication cannot be published to customers.',
        'no_followup' => 'This note has no follow-up action.',
        'organization_note_not_publishable' => 'Internal organization notes cannot be shared with customers.',
        'call_requires_external_direction' => 'For a phone call, please state whether it was inbound or outbound.',
        'direction_required' => 'Please choose a direction.',
    ],

    'badge' => [
        'confidential' => 'Confidential',
        'followup_done' => 'Done',
    ],

    'subtitle' => [
        'notes' => 'Capture notes quickly and find them again – internally or with a customer.',
    ],

    'storage' => [
        'all' => 'All filings',
        'internal' => 'Internal',
        'customer' => 'Customer',
    ],

    'filter' => [
        'search' => 'Search',
        'search_placeholder' => 'Subject or content …',
        'all_customers' => 'All customers',
        'all_types' => 'All types',
        'open_followups' => 'Open follow-ups',
    ],

    'hint' => [
        'customer_not_published' => 'The note appears in the customer record, but not in the customer portal.',
    ],

    'section' => [
        'more' => 'More details',
    ],

    'empty' => 'No communication notes yet.',
    'empty_filtered' => 'No notes found.',
    'confirm_delete' => 'Really delete this communication note?',
    'confirm_publish' => 'Really make this note visible to the customer?',
];
