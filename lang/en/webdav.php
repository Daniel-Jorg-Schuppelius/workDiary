<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webdav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'WebDAV storage',
    'intro' => 'Released documents are mirrored by document type into an external WebDAV storage (Nextcloud/ownCloud) — with a transfer record (hash, time, target). WorkDiary stays authoritative; external changes to mirrored files surface as a conflict, never a silent overwrite.',

    'conflict' => [
        'subtitle' => 'External change detected — mirroring paused (no overwrite).',
        'action' => [
            'overwrite' => 'Overwrite remote',
            'import' => 'Import as new version',
            'detach' => 'Detach mirror',
        ],
        'confirm' => [
            'overwrite' => 'Overwrite the external file with the local state? The external change will be lost.',
            'import' => 'Import the external state as a new local version?',
            'detach' => 'Permanently detach mirroring for this document? The connection stays active.',
        ],
        'flash' => [
            'overwritten' => 'External file overwritten with the local state.',
            'imported' => 'External state imported as a new local version.',
            'detached' => 'Mirroring detached for this document.',
            'failed' => 'Conflict resolution failed: :reason',
        ],
        'import_note' => 'Imported from WebDAV (conflict resolution).',
    ],

    'health' => [
        'ok' => 'Connected',
        'failing' => 'Unreachable',
        'inactive' => 'Inactive',
    ],

    'action' => [
        'mirror' => 'Mirror now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'connection' => [
        'heading' => 'Storage',
    ],

    'field' => [
        'name' => 'Label',
        'base_url' => 'Collection URL',
        'base_url_help' => 'Full WebDAV folder, e.g. .../remote.php/dav/files/USER/WorkDiary.',
        'username' => 'Username',
        'app_password' => 'App password',
        'password_keep' => '•••••••• (leave unchanged)',
        'password_help' => 'Nextcloud: Settings → Security → App password. Stored encrypted.',
        'default_folder' => 'Default folder',
        'active' => 'Active',
        'sources' => 'Mirrored content',
        'source_document' => 'Documents (DMS)',
        'source_invoice_pdf' => 'Invoices (PDF)',
        'source_protocol_pdf' => 'Protocols (PDF)',
        'sources_help' => 'Which content is mirrored to this store. No selection means released documents only.',
    ],

    'folder' => [
        'heading' => 'Document type → folder',
        'help' => 'Maps document types to a subfolder (relative to the collection URL). Without a match the default folder applies.',
        'type_placeholder' => '— document type —',
        'path_placeholder' => 'Subfolder',
    ],

    'flash' => [
        'saved' => 'WebDAV storage saved.',
        'mirror_done' => 'Mirror run started.',
        'disconnected' => 'WebDAV storage disconnected. Already mirrored files remain externally.',
        'no_connection' => 'No active WebDAV storage.',
        'invalid_url' => 'The collection URL must start with http:// or https://.',
        'password_required' => 'A new storage requires an app password.',
    ],
];
