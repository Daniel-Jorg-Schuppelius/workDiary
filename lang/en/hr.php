<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hr.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Digital personnel file (Feature 141, MVP-708).
    'personnel_file' => [
        'title' => 'Personnel file',
        'title_mine' => 'My personnel file',
        'nav' => 'My personnel file',
        'subtitle' => 'Personnel file of :name — confidential, visible only to the personnel-file circle and the person concerned.',
        'subtitle_mine' => 'Your own personnel file (self-access, read-only).',
        'back' => 'Back to staff list',
        'empty' => 'No documents in the personnel file yet.',
        'confidential_fixed' => 'Personnel files are always confidential — the switch is omitted, the flag is enforced.',
        'retention_pending' => 'from exit',
        'confirm_delete' => 'Permanently destroy this document from the personnel file? Files and versions are deleted; the audit log remains.',
        'field' => [
            'title' => 'Title',
            'category' => 'Category',
            'validity' => 'Validity',
            'valid_from' => 'Valid from',
            'valid_until' => 'Valid until',
            'retention_until' => 'Retention until',
            'version' => 'Version',
            'updated_at' => 'Updated',
            'description' => 'Description',
            'file' => 'File',
            'version_note' => 'Version note',
            'documents' => 'Documents',
        ],
        'action' => [
            'upload' => 'Add document',
            'edit' => 'Edit',
            'save' => 'Save',
            'download' => 'Download',
            'versions' => 'Versions',
            'delete' => 'Destroy',
        ],
        'flash' => [
            'created' => 'Document was added to the personnel file.',
            'updated' => 'Personnel file document was updated.',
        ],
    ],
];
