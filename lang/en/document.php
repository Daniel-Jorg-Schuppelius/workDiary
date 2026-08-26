<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : document.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Documents',
        'versions' => 'Versions',
        'version_history' => 'Version history',
    ],

    'subtitle' => 'Manage contracts, certificates, test reports and other documents.',

    'field' => [
        'title' => 'Title',
        'confidential' => 'Confidential (creator + management right only)',
        'type' => 'Type',
        'status' => 'Status',
        'reference' => 'Reference',
        'validity' => 'Validity',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'description' => 'Description',
        'file' => 'File',
        'version' => 'Version',
        'version_note' => 'Version note',
        'creator' => 'Created by',
    ],

    'action' => [
        'create' => 'Add document',
        'edit' => 'Edit',
        'save' => 'Save',
        'delete' => 'Delete',
        'archive' => 'Archive',
        'download' => 'Download',
        'add_version' => 'Upload new version',
    ],

    'filter' => [
        'all' => 'All',
        'search' => 'Search',
        'search_placeholder' => 'Search titles',
        'expiring' => 'Expiring',
        'expiring_days' => 'within :days days',
    ],

    'ref' => [
        'customer' => 'Customer',
        'project' => 'Project',
        'diary' => 'Job',
        'asset' => 'Asset',
        'user' => 'Employee',
        'none' => 'Unlinked',
    ],

    'badge' => [
        'current' => 'Current',
        'expired' => 'Expired',
        'expires_soon' => 'Expires soon',
    ],

    'flash' => [
        'created' => 'Document created.',
        'updated' => 'Document updated.',
        'deleted' => 'Document deleted.',
        'archived' => 'Document archived.',
        'version_added' => 'Version :no uploaded.',
    ],

    'error' => [
        'unknown_type' => 'Unknown document type.',
        'valid_until_before_from' => 'The end of validity must be after its start.',
    ],

    'hint' => [
        'upload' => 'Allowed: PDF, images, Office files, text/CSV, ZIP — max. :mb MB.',
    ],

    // Customer release for the customer portal (wave D — document mirroring).
    'customer' => [
        'section' => 'Customer release',
        'released' => 'Released to customer portal',
        'not_released' => 'Not released',
        'released_at' => 'Released on',
        'released_by' => 'Released by',
        'badge' => 'Portal',
        'not_linked_hint' => 'Only documents linked to a customer or a job can be released.',
        'action' => [
            'release' => 'Release to customer portal',
            'revoke' => 'Withdraw release',
        ],
        'confirm_revoke' => 'Really withdraw the customer portal release?',
        'flash' => [
            'released' => 'Document has been released to the customer portal.',
            'revoked' => 'The customer portal release has been withdrawn.',
        ],
        'error' => [
            'not_linked' => 'Only documents linked to a customer or a job can be released.',
        ],
        'portal' => [
            'title' => 'Documents',
            'subtitle' => 'The documents released to you.',
            'empty' => 'No documents have been released to you yet.',
        ],
    ],

    'empty' => 'No documents yet.',
    'empty_title' => 'No documents found',
    'empty_filtered' => 'No documents match the current filters.',
    'empty_versions' => 'No versions yet.',
    'confirm_delete' => 'Really delete this document including all versions?',
    'confirm_archive' => 'Really archive this document?',
];
