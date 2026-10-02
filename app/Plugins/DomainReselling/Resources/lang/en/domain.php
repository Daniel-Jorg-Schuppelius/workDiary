<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : domain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'health' => [
        'error' => 'Health check failed (:class).',
        'no_org_context' => 'No organization context.',
        'ok' => 'DomainReselling connections healthy.',
        'pilot_open' => 'Connection active, real pilot still open.',
    ],
    'plugin' => [
        'description' => 'Connect and controlledly manage the domains of a DomainReselling account with customers.',
    ],
    'settings' => [
        'connection_note' => 'Credentials (login/password) are managed per connection — not here.',
        'connection_note_link' => 'Go to the DomainReselling connections',
        'timeout' => 'API timeout (seconds)',
        'timeout_help' => 'Maximum wait time per provider request. Default: 20.',
        'check_budget_per_hour' => 'Check budget per hour',
        'check_budget_per_hour_help' => 'Maximum availability checks per organization and hour — protects against provider penalty points. Default: 300.',
        'check_cache_ttl' => 'Check cache (seconds)',
        'check_cache_ttl_help' => 'How long an availability result is cached; cache hits consume no budget. Default: 300.',
        'list_page_size' => 'List page size',
        'list_page_size_help' => 'Batch size of the paginated domain list queries during sync. Default: 100.',
        'stale_after_hours' => 'Stale after (hours)',
        'stale_after_hours_help' => 'Data age after which a domain projection is marked stale. Default: 24.',
        'range_error' => 'Please enter a whole number between :min and :max.',
    ],
];
