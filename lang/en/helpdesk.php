<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : helpdesk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Helpdesk und Service Desk (Feature 065, Phase 156).
return [
    'form' => [
        'noCustomer' => 'no customer',
        'noProject' => 'no project',
        'noAsset' => 'no asset',
    ],
    'service' => [
        'label' => 'Affected business service',
        'none' => 'no business service',
        'offering' => 'Service offering',
        'noOffering' => 'no offering',
        'all' => 'All business services',
    ],
    'map' => [
        'title' => 'Service map',
        'subtitle' => 'Which business services depend on each other and who is affected by an outage.',
        'empty' => 'No business services in the service catalogue yet.',
        'service' => 'Business service',
        'dependsOn' => 'Depends on',
        'dependsOnHint' => 'If one of these business services fails, this one is affected too. Select several with Ctrl or Cmd.',
        'dependents' => 'Required by',
        'affected' => 'Affected by an outage',
        'openTickets' => 'Open tickets',
        'cycle' => 'This dependency would create a cycle: a selected business service already depends on this one.',
    ],
    'impact' => [
        'title' => 'Impact',
        'none' => 'No other business service depends on “:service”.',
        'affected' => 'If “:service” fails, these business services are affected too:',
        'openTickets' => ':count open ticket|:count open tickets',
    ],
    'time' => [
        'title' => 'Time on this ticket',
        'total' => 'Total',
        'billable' => 'billable',
        'book' => 'Book time',
        'empty' => 'No time booked on this ticket yet.',
        'needsProject' => 'The ticket needs a project before time can be booked.',
        'ticketOptional' => 'Ticket (optional)',
        'noTicket' => 'No ticket',
    ],
    'routing' => [
        'conditionsHint' => 'Keys: kind, priority, source, customer_id, queue_id, sla_contract_id, business_service_id, service_offering_id, locale (e.g. "en"), business_hours ("in" or "out"). Single values or lists.',
        'actionsHint' => 'Keys: set_queue, set_priority, set_sla, set_team, assign ("round_robin" in turn or "least_loaded" by workload, within the queue\'s team).',
    ],
    'report' => [
        'month' => 'Month',
        'created' => 'Reported',
        'reactionP50' => 'Reaction P50 (h)',
        'resolutionP50' => 'Resolution P50 (h)',
        'trendCreated' => 'Reported tickets per month',
        'trendResolution' => 'Resolution time P50 per month',
        'trendTable' => 'Trend over the last twelve months',
        'byService' => 'Tickets per business service',
        'open' => 'Open',
        'breached' => 'Resolution deadline breached',
        'csvTrends' => 'CSV trend',
        'csvServices' => 'CSV business services',
    ],
    'portal' => [
        'asset' => 'Affected device',
        'noAsset' => 'no specific device',
        'service' => 'Affected service',
        'noService' => 'no specific service',
    ],
];
