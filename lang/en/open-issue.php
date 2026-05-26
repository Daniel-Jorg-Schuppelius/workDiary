<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : open-issue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Open Issues',
        'show' => 'Open Issue :title',
    ],

    'field' => [
        'title' => 'Title',
        'description' => 'Description',
        'category' => 'Category',
        'severity' => 'Severity',
        'status' => 'Status',
        'assignee' => 'Assigned to',
        'creator' => 'Created by',
        'due_at' => 'Due by',
        'visibility' => 'Visibility',
        'closed_at' => 'Closed at',
        'closed_by' => 'Closed by',
        'reason' => 'Reason',
        'resolution' => 'Resolution',
    ],

    'action' => [
        'create' => 'Create open issue',
        'edit' => 'Edit',
        'assign' => 'Assign',
        'start' => 'Move to in progress',
        'block' => 'Block',
        'unblock' => 'Unblock',
        'complete' => 'Complete',
        'wontDo' => 'Won\'t do',
        'reopen' => 'Reopen',
        'delete' => 'Delete',
        'publishToCustomer' => 'Share with customer',
    ],

    'flash' => [
        'created' => 'Open issue created.',
        'updated' => 'Open issue updated.',
        'deleted' => 'Open issue deleted.',
        'assigned' => 'Assignment updated.',
        'status' => [
            'open' => 'Open issue reopened.',
            'inProgress' => 'Open issue is now in progress.',
            'blocked' => 'Open issue has been blocked.',
            'done' => 'Open issue has been completed.',
            'wontDo' => 'Open issue marked as won\'t do.',
            'reopened' => 'Open issue has been reopened.',
        ],
    ],
];
