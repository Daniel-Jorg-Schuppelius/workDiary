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
        'upload' => 'Allowed: PDF, images, Office files, text/CSV, ZIP — max. 25 MB.',
    ],

    'empty' => 'No documents yet.',
    'empty_title' => 'No documents found',
    'empty_filtered' => 'No documents match the current filters.',
    'empty_versions' => 'No versions yet.',
    'confirm_delete' => 'Really delete this document including all versions?',
    'confirm_archive' => 'Really archive this document?',
];
