<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Leads (Phase 156).
return [
    'report' => [
        'title' => 'Lead analysis',
        'subtitle' => 'Leads by source: added in the period, status and conversion.',
        'total' => 'Leads',
        'open' => 'open',
        'converted' => 'converted',
        'discarded' => 'discarded',
        'rate' => 'Conversion rate',
        'avgDays' => 'Avg. days to conversion',
        'empty' => 'No leads in this period.',
    ],
    'quote' => [
        'action' => 'Create quote',
        'confirm' => 'The lead will be created as a customer. Continue?',
        'title' => 'Quotes from this lead',
    ],
    'history' => [
        'title' => 'History',
        'empty' => 'No events yet.',
    ],
];
