<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : paperless.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Brings documents from a self-hosted Paperless-ngx instance into cloud document intake, read-only: either all documents or those of one tag, correspondent or document type. The intake rules route them to incoming invoices or document storage.',
    ],
    'settings' => [
        'base_url' => 'Base URL',
        'base_url_help' => 'Address of the Paperless-ngx instance, e.g. https://paperless.example.com (without /api).',
        'api_token' => 'API token',
        'api_token_help' => 'Create it in Paperless-ngx under “My Profile”. The token reads with the permissions of its user; WorkDiary changes nothing in Paperless. View permission is required for documents, tags, correspondents, document types and “UISettings”; only documents the user may see are visible.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a base URL with a private or internal address. Logged, and only effective if the operator permits it.',
    ],
    'action' => [
        'connect' => 'Connect Paperless-ngx',
    ],
    'connect' => [
        'title' => 'Connect Paperless-ngx',
        'legend' => 'Connection',
        'instance' => 'Instance from the plugin settings: :instance',
        'name' => 'Connection name',
        'hint' => 'Then choose the source (all documents, tag, correspondent or document type), “/” or a document type as root folder, and create rules. A document’s path is document type/correspondent/file name.',
        'submit' => 'Check connection',
        'not_configured' => 'First enter the base URL and API token in the plugin settings.',
        'to_settings' => 'Go to plugin settings',
    ],
    'container' => [
        'all' => 'All documents',
        'kind' => [
            'tag' => 'Tag',
            'correspondent' => 'Correspondent',
            'type' => 'Document type',
        ],
    ],
    'flash' => [
        'not_configured' => 'Paperless-ngx is not set up: base URL or API token missing in the plugin settings.',
        'instance_changed' => 'The plugin settings point to a different Paperless instance than this connection. Disconnect it first; documents already taken over are kept.',
    ],
    'health' => [
        'not_configured' => 'Paperless-ngx is not set up (base URL or token missing).',
        'not_connected' => 'No Paperless connection in cloud document intake yet.',
        'attention' => 'The Paperless connection reports an error — see cloud document intake.',
    ],
];
