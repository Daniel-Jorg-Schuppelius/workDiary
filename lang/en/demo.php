<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : demo.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Content of the demo blocks (MVP-1532/1533). Stored in the language of whoever runs the seed.
return [
    'schedule' => [
        'shift_type' => [
            'early' => 'Early shift',
            'late' => 'Late shift',
            'office' => 'Office shift',
        ],
        'plan_current' => 'Duty roster this week and next (demo)',
        'plan_next' => 'Duty roster for the week after next (demo)',
        'coverage_note' => 'At least two people are scheduled for the early shift on weekdays.',
        'exchange_reason' => 'Private appointment in the afternoon, swap agreed with the early shift.',
    ],
    'forms' => [
        'report' => [
            'name' => 'Service report (demo)',
            'description' => 'Short report after every visit at the customer.',
            'work_done' => 'Work carried out',
            'work_done_value' => 'Maintenance carried out according to the checklist, no defects found.',
            'hours_on_site' => 'Time on site',
            'result' => 'Result',
            'result_done' => 'Completed',
            'result_follow_up' => 'Follow-up visit required',
            'result_material' => 'Material missing',
            'briefed' => 'Customer briefed',
            'satisfaction' => 'Customer satisfaction',
        ],
        'safety' => [
            'name' => 'Risk assessment before starting work (demo)',
            'description' => 'Quick check on site before work begins.',
            'ppe_complete' => 'Protective equipment complete',
            'area_secured' => 'Work area secured',
            'hazards' => 'Particular hazards',
            'hazards_value' => 'Customer operations ongoing, work area cordoned off with barrier tape.',
        ],
        'handover' => [
            'name' => 'Handover before holiday (demo)',
            'description' => 'Draft: open tasks and contacts for the stand-in.',
            'open_tasks' => 'Open tasks',
            'contact' => 'Contact person at the customer',
        ],
    ],
    'knowledge' => [
        'guide_title' => 'Guide: :subject',
        'guide_solution' => 'Acceptance procedure, go through each point with the customer:',
        'issue_solution' => 'Agree on a date with the customer, carry out the check and document the result in the order. Then close the open item.',
        'onboarding_title' => 'First week for new colleagues',
        'onboarding_problem' => 'New staff do not know where orders, times and documents are kept.',
        'onboarding_solution' => 'Draft: walkthrough of orders, time tracking and the knowledge base; a fixed contact person for the first two weeks.',
    ],
    'inventory' => [
        'warehouse_main' => 'Main warehouse (demo)',
        'warehouse_main_note' => 'Shelf rows A–C on the ground floor',
        'warehouse_vehicle' => 'Service vehicle 1 (demo)',
    ],
    'travel' => [
        'vendor' => [
            'parking' => 'City centre car park',
            'lodging' => 'Hotel am Markt',
            'supplies' => 'Sample hardware store',
            'hospitality' => 'Sample restaurant',
        ],
        'expense' => [
            'parking' => 'Parking for the customer appointment in :city',
            'hotel' => 'Overnight stay for the assignment in :city',
            'material' => 'Fixing material for the assignment',
            'hospitality' => 'Business lunch with the customer',
        ],
        'from_address' => 'Head office',
        'trip_purpose' => 'Customer appointment at :customer',
    ],
    'location' => [
        'zone_label' => ':customer (demo work site)',
    ],
    'contracts' => [
        'maintenance_title' => 'Maintenance contract :customer (demo)',
        'maintenance_notes' => 'Monthly flat fee, renewed for twelve months unless terminated.',
        'review_title' => 'Annual review of the scope of services',
        'license_title' => 'Office software licence (demo)',
        'license_partner' => 'Sample Software Ltd.',
        'payment_title' => 'Check the annual licence invoice',
        'rent_title' => 'Lease for storage space (demo)',
        'rent_partner' => 'Sample Business Park Ltd.',
    ],
    'facility' => [
        'site_name' => ':customer – main site',
        'street' => '1 Sample Street',
        'building' => 'Main building',
        'floor' => [
            'ground' => 'Ground floor',
            'first' => 'First floor',
        ],
        'room' => [
            'reception' => 'Reception',
            'technical' => 'Technical room',
            'storage' => 'Storage',
            'office' => 'Office',
            'meeting' => 'Meeting room',
            'social' => 'Staff room',
        ],
        'access_note' => 'Access only with the key from reception.',
    ],
    'bau' => [
        'boq_name' => 'Bill of quantities :project (demo)',
        'section_supply' => 'Supply',
        'section_labour' => 'Installation and labour',
        'item_assembly' => 'Installation and commissioning',
        'item_assembly_long' => 'Installation of all supply items including function test and briefing.',
        'item_hourly' => 'Daywork as evidenced',
        'progress_note' => 'First measurement after delivery',
    ],
    'team' => [
        'description' => 'Work on project :project',
    ],
];
