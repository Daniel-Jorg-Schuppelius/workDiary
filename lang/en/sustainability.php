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
        'statement' => 'Statement for the excerpt',
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
    // Klimanachweise und Umweltaussagen (MVP-961).
    'offset' => [
        'title' => 'Climate certificates',
        'subtitle' => 'Offsets, guarantees of origin and climate contributions with standard, quantity and retirement.',
        'separate' => 'Certificates are reported separately and never deducted from emissions.',
        'list' => 'Certificates',
        'public_title' => 'Climate certificates (not offset)',
        'add' => 'Record certificate',
        'empty' => 'No certificates yet.',
        'totals' => 'Total per year',
        'evidenced' => 'evidenced',
        'not_evidenced' => 'Retirement or registry reference missing',
        'confirm_delete' => 'Delete certificate?',
        'kind' => [
            'compensation' => 'Offset',
            'green_energy' => 'Guarantee of origin (green power)',
            'contribution' => 'Climate contribution',
        ],
        'field' => [
            'kind' => 'Type',
            'provider' => 'Provider',
            'standard' => 'Standard',
            'project_name' => 'Project',
            'quantity_t' => 'Quantity (t CO₂e)',
            'claim_year' => 'Year',
            'vintage_year' => 'Vintage',
            'retired_on' => 'Retired on',
            'registry_reference' => 'Registry reference',
            'note' => 'Note',
            'evidence' => 'Evidence',
        ],
        'hint' => [
            'standard' => 'e.g. Gold Standard, VCS, GO',
        ],
        'flash' => [
            'saved' => 'Certificate recorded.',
            'deleted' => 'Certificate deleted.',
        ],
    ],
    'claim' => [
        'title' => 'Check environmental claims',
        'intro' => 'Checks texts for wording that is prohibited or requires evidence under the EmpCo Directive (EU 2024/825, from 27 Sept 2026).',
        'text' => 'Text',
        'check' => 'Check',
        'none' => 'No conspicuous wording found.',
        'hint' => 'Checked for prohibited environmental claims when publishing.',
        'warning' => 'Published, but please review: :terms',
        'disclaimer' => 'Automatic notice, not a legal review.',
        'reason' => [
            'offset_neutrality' => 'Claims of climate neutrality or impact based on offsetting are prohibited.',
            'generic' => 'Generic environmental claim: only permitted with recognised excellent environmental performance.',
            'evidence' => 'The claim needs verifiable evidence.',
        ],
    ],
];
