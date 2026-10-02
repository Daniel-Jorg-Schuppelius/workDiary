<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : jtl_wawi.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'JTL-Wawi',
    'intro' => 'Connects JTL-Wawi as the leading inventory management system: article and warehouse projection, stock reads, and idempotent stock posting hand-over.',
    'beta_notice' => 'The JTL-Wawi API is in its beta/pilot programme. After the official release, availability may depend on the booked JTL edition and become chargeable.',

    'mode' => [
        'on_premise' => 'OnPremise',
        'cloud' => 'Cloud gateway',
    ],

    'status' => [
        'draft' => 'Draft',
        'pending_registration' => 'Registration pending',
        'active' => 'Active',
        'blocked' => 'Blocked',
        'disconnected' => 'Disconnected',
    ],

    'field' => [
        'base_url' => 'Wawi API base URL',
        'base_url_help' => 'e.g. https://wawi.example.local:5883/api/eazybusiness — the API instance is created in the JTL administrator.',
        'api_version' => 'API version',
        'detected_version' => 'Detected Wawi version',
        'company_id' => 'Company (x-companyid)',
        'company_id_help' => 'Optional: tenant/company within the Wawi.',
        'tenant_id' => 'Tenant ID',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'secret_keep' => '(unchanged — leave blank)',
        'allow_private_network' => 'Explicitly allow private/internal addresses',
        'allow_private_network_help' => 'An OnPremise Wawi typically lives on your own network. This approval is audited and applies to this connection only.',
        'last_sync' => 'Last synchronisation',
        'last_error' => 'Last error',
    ],

    'stats' => [
        'linked_articles' => 'Mapped articles',
        'open_inbox' => 'Open mapping cases',
    ],

    'scopes' => [
        'missing' => 'Missing read scopes: :scopes — adjust the app approval in JTL-Wawi and re-check the registration.',
        'missing_write' => 'Without the write scope (:scopes) stock hand-over stays disabled.',
    ],

    'registration' => [
        'heading' => 'App registration',
        'explain' => 'Open “Admin > App registration” in JTL-Wawi, then start the registration here. The API key is issued once after approval and stored encrypted.',
        'waiting' => 'The registration is waiting for approval in JTL-Wawi. After confirming, check the status here.',
    ],

    'connection' => [
        'heading' => 'Connection',
    ],

    'sync' => [
        'section' => 'Section',
        'counters' => 'Counters',
        'warehouses' => 'Warehouses',
        'articles' => 'Articles',
        'stocks' => 'Stock changes',
    ],

    'warehouses' => [
        'heading' => 'Warehouse mapping',
        'empty' => 'No JTL warehouses projected yet — synchronise first.',
        'jtl' => 'JTL warehouse',
        'type' => 'Type',
        'flags' => 'Flags',
        'local' => 'WorkDiary warehouse',
        'inactive' => 'inactive',
        'lock_shipment' => 'Shipment lock',
        'lock_availability' => 'Availability lock',
        'unmapped' => '— not mapped —',
    ],

    'inventory' => [
        'heading' => 'Inventory leadership',
        'explain' => 'Defines which system leads the stock. Switching back to “local” imports the JTL stock as an opening stocktake.',
        'mode_local' => 'Local — WorkDiary manages stock itself.',
        'mode_external' => 'External — JTL-Wawi leads; WorkDiary reads and hands over postings.',
        'mode_read_only' => 'Read only — JTL-Wawi leads; WorkDiary only displays stock.',
    ],

    'action' => [
        'save' => 'Save',
        'sync_now' => 'Synchronise now',
        'disconnect' => 'Disconnect',
        'start_registration' => 'Start registration',
        'check_registration' => 'Check approval',
        'map' => 'Map',
        'change_mode' => 'Change mode',
    ],

    'confirm' => [
        'disconnect' => 'Really disconnect? Mappings and projections are kept, the credentials are deleted.',
        'mode_change' => 'Really change the inventory leadership mode?',
    ],

    'flash' => [
        'saved' => 'Connection saved.',
        'cloud_connected' => 'Cloud connection established and token obtained.',
        'cloud_failed' => 'Cloud connection failed — check credentials and tenant ID.',
        'registration_started' => 'Registration sent — approve it in JTL-Wawi now.',
        'registration_failed' => 'Registration failed.',
        'registration_pending' => 'Approval is still pending.',
        'registration_accepted' => 'Approved — API key stored.',
        'registration_rejected' => 'The registration was rejected in JTL-Wawi.',
        'not_active' => 'The connection is not active.',
        'sync_done' => 'Synchronisation finished.',
        'sync_failed' => 'Synchronisation failed (:reason).',
        'warehouse_mapped' => 'Warehouse mapping saved.',
        'disconnected' => 'Disconnected.',
        'disconnect_blocked' => 'Cannot disconnect: switch inventory leadership to “local” first.',
        'mode_unchanged' => 'This mode is already active.',
        'mode_needs_connection' => 'External inventory leadership requires an active JTL connection.',
        'mode_needs_mapping' => 'External inventory leadership requires at least one mapped JTL warehouse.',
        'mode_changed' => 'Inventory leadership mode changed.',
        'mode_changed_with_takeover' => 'Mode changed — :booked opening corrections imported from JTL.',
        'takeover_done' => 'Opening stocktake finished: :booked corrections from :pairs pairs.',
        'takeover_failed' => 'Opening stocktake failed (:reason).',
    ],
];
