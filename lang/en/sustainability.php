<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sustainability.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nachhaltigkeit: Standorte, Benchmarking, Auszug (MVP-929/930).
return [
    'site' => [
        'benchmark' => 'Site comparison',
        'subtitle' => 'Emissions per site and year from the related activity data, with intensities per m² and per employee. Activities without a factor are not counted.',
        'back' => 'Sustainability',
        'create' => 'Add site',
        'edit' => 'Edit',
        'save' => 'Save',
        'inactive' => 'inactive',
        'empty' => 'No sites yet — add sites and record activities with a site.',
        'field' => [
            'site' => 'Site',
            'code' => 'Code',
            'year' => 'Year',
            'area_m2' => 'Area (m²)',
            'headcount' => 'Employees',
            'co2e_t' => 'CO₂e (t)',
            'per_m2' => 'kg CO₂e per m²',
            'per_head' => 'kg CO₂e per person',
            'missing' => 'without factor',
            'active' => 'active',
        ],
        'flash' => [
            'saved' => 'Site saved.',
        ],
    ],
    'excerpt' => [
        'title' => 'Public excerpt',
        'intro' => 'Release a frozen report snapshot. It is shown via a link and as a notice in the customer portal — without any compliance or climate-neutrality claim.',
        'snapshot' => 'Released snapshot',
        'none' => '— not released —',
        'with_targets' => 'Include targets',
        'publish' => 'Save release',
        'token_once' => 'Link visible only now — please copy it.',
        'link' => 'Public link',
        'state_none' => 'not issued',
        'state_active' => 'active',
        'state_paused' => 'paused',
        'pause' => 'Pause',
        'resume' => 'Resume',
        'revoke' => 'Revoke',
        'rotate' => 'Issue new link',
        'issue' => 'Issue link',
        'public_title' => 'Sustainability excerpt :org',
        'period' => 'Period :from – :to',
        'emissions' => 'Greenhouse gas emissions',
        'scope' => 'Scope :scope',
        'targets' => 'Targets',
        'disclaimer' => 'Frozen figures from the recorded activity data; no compliance or climate-neutrality claim.',
        'factors' => 'Factor sets: :sets.',
        'portal_subject' => 'Sustainability excerpt :from – :to',
        'portal_body' => 'Greenhouse gas emissions in the period: :tonnes t CO₂e.',
        'flash' => [
            'published' => 'Release saved.',
            'issued' => 'Link issued.',
            'revoked' => 'Link revoked.',
            'saved' => 'Setting saved.',
        ],
    ],
    // Vergleich nach Kundengruppe (MVP-949).
    'customer_group' => [
        'title' => 'Emissions by customer group :year',
        'group' => 'Customer group',
        'customers' => 'Customers',
        'per_customer' => 'per customer',
        'none' => 'No customer group',
        'customer' => 'Customer (for the comparison by customer group)',
        'empty' => 'No customer-related activities this year.',
    ],
];
