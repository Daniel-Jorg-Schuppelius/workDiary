<?php
/*
 * Created on   : Sun Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : permit.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Permits',
    'subtitle' => 'Official permits for events – status, deadlines and supporting documents.',
    'label' => 'Permit',
    'create' => 'Add permit',
    'edit' => 'Edit permit',
    'delete_confirm' => 'Really delete this permit?',

    'sections' => [
        'base' => 'Details',
        'dates' => 'Deadlines',
    ],

    'fields' => [
        'title' => 'Title',
        'status' => 'Status',
        'event' => 'Event',
        'event_none' => '— none —',
        'permit_type' => 'Permit type',
        'authority' => 'Authority',
        'reference_no' => 'Reference no.',
        'applied_at' => 'Applied on',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until / deadline',
        'notes' => 'Notes',
        'evidence' => 'Supporting document',
    ],

    'filter' => [
        'all_status' => 'All statuses',
    ],

    'status' => [
        'required' => 'Required',
        'applied' => 'Applied',
        'granted' => 'Granted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ],

    'messages' => [
        'created' => 'Permit created.',
        'updated' => 'Permit updated.',
        'deleted' => 'Permit deleted.',
    ],

    'evidence' => [
        'upload' => 'Upload document',
        'replace' => 'Replace document',
        'replace_hint' => 'A new upload replaces the existing document.',
        'hint' => 'Allowed: PDF, JPG, PNG, DOCX (max. 25 MB).',
        'remove' => 'Remove document',
        'remove_confirm' => 'Really remove the supporting document?',
        'too_large' => 'The file is too large (max. 25 MB).',
        'invalid_type' => 'File type not allowed (PDF, JPG, PNG, DOCX).',
    ],
];
