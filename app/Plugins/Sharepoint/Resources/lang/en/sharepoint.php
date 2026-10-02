<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sharepoint.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'SharePoint storage',
    'intro' => 'Released documents are mirrored by document type into a SharePoint document library via Microsoft Graph — with a transfer proof (hash, time, target). WorkDiary stays authoritative; external changes to mirrored files surface as conflicts, never silently adopted.',
    'plugin_description' => 'Mirrors released documents into a SharePoint document library via Microsoft Graph — with transfer proof and conflict display, no return channel.',
    'not_configured_hint' => 'SHAREPOINT_CLIENT_ID/SECRET (or the MSGRAPH_* fallback values) are not set — the connection can only be established after the app registration in the Microsoft tenant.',

    'health' => [
        'badge_ok' => 'Connected',
        'badge_failing' => 'Unreachable',
        'badge_inactive' => 'Inactive',
        'not_configured' => 'SharePoint is not configured (SHAREPOINT_/MSGRAPH_CLIENT_ID/SECRET missing).',
        'no_org_context' => 'Configured (no organization in context).',
        'no_connection' => 'No SharePoint connection established.',
        'inactive' => 'SharePoint connection is disconnected, paused or has no target library.',
        'ok' => 'Connected — target library reachable.',
        'failing' => 'Microsoft Graph unreachable or access denied.',
        'error' => 'Microsoft Graph error (:class).',
    ],

    'action' => [
        'connect' => 'Connect with Microsoft 365',
        'mirror' => 'Mirror now',
        'disconnect' => 'Disconnect',
        'save' => 'Save',
    ],

    'target' => [
        'heading' => 'Target: site + document library',
        'help' => 'Search for a site first, then choose the document library. Both are validated server-side via Microsoft Graph — with Sites.Selected only granted sites appear.',
        'current' => 'Current target',
        'search' => 'Search site',
        'search_placeholder' => 'Site name or keyword',
        'search_action' => 'Search',
        'no_sites' => 'No sites found (check the search term; with Sites.Selected the tenant admin must grant the site).',
        'selected' => 'Selected',
        'drive' => 'Document library',
        'no_drives' => 'No document libraries found in this site.',
    ],

    'settings' => [
        'heading' => 'Folder rules + sources',
    ],

    'field' => [
        'default_folder' => 'Default folder',
        'active' => 'Active',
        'sources' => 'Mirrored content',
        'source_document' => 'Documents (DMS)',
        'source_invoice_pdf' => 'Invoices (PDF)',
        'source_protocol_pdf' => 'Protocols (PDF)',
        'sources_help' => 'Which content is mirrored into this library. Without a selection only released documents.',
    ],

    'folder' => [
        'heading' => 'Document type → folder',
        'help' => 'Maps document types to a subfolder (relative to the library). Without a match the default folder applies.',
        'type_placeholder' => '— document type —',
        'path_placeholder' => 'Subfolder',
    ],

    'conflict' => [
        'subtitle' => 'External change detected — mirroring paused (no overwrite).',
        'action' => [
            'overwrite' => 'Overwrite remote',
            'import' => 'Import as new version',
            'detach' => 'Detach mirroring',
        ],
        'confirm' => [
            'overwrite' => 'Overwrite the external file with the local state? The external change will be lost.',
            'import' => 'Adopt the external state as a new local version?',
            'detach' => 'Permanently detach mirroring for this document? The connection stays active.',
        ],
        'flash' => [
            'overwritten' => 'External file overwritten with the local state.',
            'imported' => 'External state imported as a new local version.',
            'detached' => 'Mirroring for this document detached.',
            'failed' => 'Conflict resolution failed: :reason',
        ],
        'import_note' => 'Imported from SharePoint (conflict resolution).',
    ],

    'flash' => [
        'not_configured' => 'SharePoint is not configured (client ID/secret missing).',
        'state_invalid' => 'The OAuth flow expired or is invalid — please connect again.',
        'oauth_denied' => 'Microsoft did not return an authorization code (flow cancelled?).',
        'oauth_failed' => 'Token exchange failed (:class).',
        'connected' => 'Connected with Microsoft 365. Now choose site + library.',
        'disconnected' => 'SharePoint connection disconnected. Already mirrored files remain externally.',
        'no_connection' => 'No active SharePoint connection available.',
        'site_invalid' => 'The chosen site is unreachable or not granted.',
        'drive_invalid' => 'The chosen document library does not belong to the chosen site.',
        'target_saved' => 'Target library saved.',
        'saved' => 'SharePoint settings saved.',
        'mirror_done' => 'Mirror run started.',
    ],
];
