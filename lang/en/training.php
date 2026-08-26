<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : training.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'section' => 'Training',
    'nav' => [
        'courses' => 'Course catalogue',
        'requirements' => 'Requirement matrix',
        'assignments' => 'Training plan',
    ],
    'title' => [
        'courses' => 'Course catalogue',
        'requirements' => 'Requirement matrix',
        'assignments' => 'Training plan',
    ],
    'subtitle' => [
        'courses' => 'Courses with provider, duration, validity and legal basis — records stay in the safety register.',
        'requirements' => 'Which role or work area owes which course; this creates the plan per person.',
        'assignments' => 'Who owes which training by when — with the record from the instruction.',
    ],

    'field' => [
        'code' => 'Course code',
        'title' => 'Title',
        'provider_kind' => 'Provider',
        'provider_name' => 'Provider name',
        'duration_minutes' => 'Duration (minutes)',
        'validity_months' => 'Validity (months)',
        'is_mandatory' => 'Mandatory training',
        'legal_basis' => 'Legal basis',
        'cost' => 'Cost',
        'cost_amount' => 'Cost (informational)',
        'cost_currency' => 'Currency',
        'lead_days' => 'Lead time (days)',
        'notes' => 'Notes',
        'is_active' => 'Active',
        'course' => 'Course',
        'version' => 'Course version',
        'versions' => 'Course versions',
        'version_label' => 'Version label',
        'valid_from' => 'Valid from',
        'content_summary' => 'Content summary',
        'subject' => 'Target group',
        'subject_kind' => 'Target group type',
        'subject_role' => 'Role',
        'subject_team' => 'Work area (team)',
        'first_due_days' => 'First due (days)',
        'user' => 'Person',
        'due_at' => 'Due on',
        'fulfilled_at' => 'Recorded on',
        'proof' => 'Record',
        'state' => 'State',
        'source' => 'Origin',
        'requirements_count' => 'Assignments',
        'assignments_count' => 'Plan entries',
    ],

    'action' => [
        'create_course' => 'Add course',
        'create_requirement' => 'Add requirement',
        'create_assignment' => 'Add plan entry',
        'create_version' => 'Add course version',
        'sync_assignments' => 'Refresh plan',
        'edit' => 'Edit',
        'save' => 'Save',
        'delete' => 'Delete',
        'show' => 'View',
        'back' => 'Back',
    ],

    'filter' => [
        'all' => 'All',
        'mandatory_only' => 'Mandatory only',
        'state' => 'State',
        'subject_kind' => 'Target group',
    ],

    'kpi' => [
        'mandatory' => 'Mandatory courses',
        'active_requirements' => 'Active requirements',
        'overdue' => 'Overdue',
    ],

    'empty' => [
        'courses' => 'No course in the catalogue yet.',
        'versions' => 'No course version yet.',
        'requirements' => 'No requirement assigned yet.',
        'assignments' => 'No training plan entries yet.',
    ],

    'hint' => [
        'cost_informational' => 'Costs are informational only — no posting and no document is created.',
        'instruction_course' => 'With a course reference this attendance counts as the record for the training plan.',
        'no_second_guard' => 'The training plan notifies and reports; blocking stays with the qualification status.',
        'proof_in_register' => 'Records are captured exclusively as instructions in the safety register.',
        'sync' => 'The refresh creates missing plan entries and removes obsolete ones without a record.',
    ],

    'confirm' => [
        'delete_course' => 'Delete course?',
        'delete_version' => 'Delete course version?',
        'delete_requirement' => 'Delete requirement?',
        'delete_assignment' => 'Delete plan entry?',
    ],

    'flash' => [
        'course_created' => 'Course created.',
        'course_updated' => 'Course updated.',
        'course_deleted' => 'Course deleted.',
        'version_created' => 'Course version created.',
        'version_deleted' => 'Course version deleted.',
        'requirement_created' => 'Requirement created.',
        'requirement_updated' => 'Requirement updated.',
        'requirement_deleted' => 'Requirement deleted.',
        'assignment_created' => 'Plan entry created.',
        'assignment_deleted' => 'Plan entry deleted.',
        'assignments_synced' => 'Plan refreshed: :created added, :removed removed.',
    ],

    'error' => [
        'delete_with_proof' => 'This course has records — it can only be deactivated.',
        'delete_last_version' => 'The last course version cannot be deleted.',
        'delete_version_in_use' => 'This course version is referenced by an instruction record and stays.',
    ],

    'report' => [
        'title' => 'Training report',
        'nav' => 'Training',
        'subtitle' => 'Compliance rate per team, role and course on the reference date — the basis of competence evidence.',
        'total' => 'Total',
        'team' => 'Team',
        'role' => 'Role',
        'course' => 'Course',
        'no_team' => 'Without team',
        'no_role' => 'Without role',
        'rate' => 'Compliance rate',
        'rate_by_team' => 'Compliance rate per team',
        'rate_by_course' => 'Compliance rate per course',
        'by_team' => 'By team',
        'by_role' => 'By role',
        'by_course' => 'By course',
        'kpi' => [
            'assignments' => 'Plan entries',
            'fulfilled' => 'Compliant',
            'due' => 'Due',
            'overdue' => 'Overdue',
            'rate' => 'Compliance rate',
        ],
        'empty' => 'No training plan entries for the selected filter.',
    ],
];
