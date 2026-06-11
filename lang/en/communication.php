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
    ],

    'badge' => [
        'confidential' => 'Confidential',
        'followup_done' => 'Done',
    ],

    'empty' => 'No communication notes yet.',
    'confirm_delete' => 'Really delete this communication note?',
    'confirm_publish' => 'Really make this note visible to the customer?',
];
