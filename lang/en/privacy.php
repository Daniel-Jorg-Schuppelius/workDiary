<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'retention_area' => [
        'audit_logs' => 'Audit log',
        'exports' => 'Payroll and time exports',
        'gobd_financial' => 'Tax-relevant data',
        'claims' => 'Complaints (closed)',
        'leads' => 'Leads (not converted)',
        'applications' => 'Applications (rejected/withdrawn)',
        'privacy_requests' => 'Data subject requests (closed)',
        'cti_calls' => 'CTI call metadata',
        'idea_maps' => 'Idea maps (recycle bin)',
        'dictations' => 'Voice dictations (transcripts)',
        'problem_reports' => 'Problem reports (closed)',
        'driver_license_checks' => 'Driver licence checks',
        'documents_invoice' => 'Invoices (DMS)',
        'passenger_rides' => 'Ride records (location and passenger data)',
        'employee_records' => 'Employee master data (former employees)',
        'personnel_files' => 'Personnel files (former employees)',
        'customer_master' => 'Customer master data (no transactions)',
        'customer_intakes' => 'Customer intakes (rejected or withdrawn)',
        'time_records' => 'Raw working time data (attendance records)',
        'location_points' => 'Raw location data (GPS points)',
        'documents_general' => 'Documents (not relevant to tax or commercial law)',
        'learning_records' => 'Learning platform (enrollments, learning time, attempts)',
        'learning_certificates' => 'Learning platform certificates (pseudonymisation)',
    ],

    'requirement' => [
        'avv_required' => 'DPA with processor',
        'avv_current' => 'DPA valid (not expired)',
        'gvv_required' => 'Joint controller agreement with joint controller',
        'dpia_required' => 'DPIA where a DPIA is required',
        'tom_assigned' => 'TOM per processing activity',
        'tom_proof_current' => 'TOM evidence valid (not expired)',
    ],
    // Datenkategorien der Datenschutz-Übersicht (Schlüssel == config/privacy.php categories.*.code).
    'category' => [
        'employees' => ['label' => 'Employees', 'retention' => 'until the end of the contract', 'delete_path' => 'Org admin → Members'],
        'working_time' => ['label' => 'Working time', 'retention' => '10 years (GoBD)', 'delete_path' => 'locked after the lock, cannot be deleted'],
        'absences' => ['label' => 'Payroll absences', 'retention' => 'according to collective agreement/law', 'delete_path' => 'Org admin after the retention period'],
        'diary' => ['label' => 'Order book', 'retention' => '5 years (configurable)', 'delete_path' => 'Org admin'],
        'tours' => ['label' => 'Tours / locations', 'retention' => '2 years (suggestion)', 'delete_path' => 'automatic deletion run'],
        'expenses' => ['label' => 'Expenses / travel costs', 'retention' => '10 years (GoBD)', 'delete_path' => 'locked, archived'],
        'customers' => ['label' => 'Customer master data', 'retention' => 'until the end of the business relationship + retention period', 'delete_path' => 'Org admin'],
        'attachments' => ['label' => 'Attachments', 'retention' => 'with the parent record', 'delete_path' => 'together with the parent record'],
        'signatures' => ['label' => 'Signatures', 'retention' => 'like the order', 'delete_path' => 'together with the order'],
        'qualifications' => ['label' => 'Qualifications', 'retention' => 'until the end of the contract', 'delete_path' => 'Org admin'],
        'push' => ['label' => 'Push subscriptions', 'retention' => 'on unsubscribing', 'delete_path' => 'automatically on sign-out/cleanup'],
        'audit' => ['label' => 'Audit log', 'retention' => '24 months (suggestion)', 'delete_path' => 'system rotation job'],
    ],
    'retention_years' => ':years years',
    // Befunde der Lückenanalyse (ComplianceAnalysisService, gespeichert in der Sprache der Organisation).
    'gap' => [
        'avv_missing' => 'Processor “:name” without DPA',
        'avv_expiring' => 'DPA “:name” is expiring or has expired',
        'gvv_missing' => 'Joint controller “:name” without joint controller agreement',
        'dpia_missing' => '“:name” requires a DPIA but has no completed DPIA',
        'tom_missing' => '“:name” without assigned TOMs',
        'tom_proof_expiring' => 'TOM evidence is expiring or has expired: :names',
    ],
];
