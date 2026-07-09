<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maintenance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'window' => [
        'title' => 'Maintenance windows',
        'subtitle' => 'Announce, start, extend and traceably complete planned downtimes.',
        'read_only_message' => 'Maintenance: the application is temporarily read-only.',
        'scope' => [
            'system' => 'Installation-wide',
            'organization' => 'This organisation only',
        ],
        'mode' => [
            'full' => 'Full lock',
            'read_only' => 'Read-only',
            'block_ingest' => 'Ingest blocked',
            'read_only_toggle' => 'Read-only mode (read access remains possible)',
            'block_ingest_toggle' => 'Block terminal/CTI/location ingest during maintenance',
        ],
        'status' => [
            'planned' => 'Planned',
            'announced' => 'Announced',
            'active' => 'Active',
            'extended' => 'Extended',
            'completed' => 'Completed',
            'rolled_back' => 'Rolled back',
            'cancelled' => 'Cancelled',
        ],
        'field' => [
            'window' => 'Time window',
            'scope' => 'Scope',
            'mode' => 'Mode',
            'status' => 'Status',
            'actions' => 'Actions',
            'announce_from' => 'Announce from',
            'starts_at' => 'Start',
            'ends_at' => 'End',
            'message' => 'Notice text',
        ],
        'action' => [
            'plan' => 'Plan maintenance window',
            'save' => 'Plan',
            'announce' => 'Announce',
            'start' => 'Start now',
            'complete' => 'Complete',
            'extend' => 'Extend',
            'rollback' => 'Rolled back',
            'cancel' => 'Cancel',
        ],
        'banner' => [
            'upcoming' => 'Planned maintenance: :from to :to — please save your work in time.',
            'read_only' => 'Maintenance active until :to — changes are temporarily not possible.',
        ],
        'hint' => [
            'message' => 'Optional: what is being maintained, what to expect?',
        ],
        'empty' => [
            'title' => 'No maintenance windows',
            'message' => 'No maintenance windows are planned.',
        ],
        'flash' => [
            'planned' => 'Maintenance window planned.',
            'announce' => 'Maintenance window announced.',
            'start' => 'Maintenance window started.',
            'complete' => 'Maintenance window completed.',
            'extend' => 'Maintenance window extended.',
            'rollback' => 'Maintenance completed as rollback.',
            'cancel' => 'Maintenance window cancelled.',
        ],
    ],
];
