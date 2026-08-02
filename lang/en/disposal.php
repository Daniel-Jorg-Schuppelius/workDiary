<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : disposal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Disposal case (feature 100, MVP-474/475): list, case, dialogs and
// customer record PDF. Enum labels and backend messages live inline in code.
return [
    'eyebrow' => 'Disposal',

    'index' => [
        'title' => 'Disposal cases',
        'subtitle' => 'Pickup, device list, data media treatment and disposal contractor proofs — audit-proof up to the customer record.',
        'empty' => 'No disposal cases — create the first case via the dialog.',
        'kpi' => [
            'open' => 'Open cases',
            'hazardous_open' => 'Open with hazardous waste',
            'completed_year' => 'Completed (current year)',
        ],
        'filter' => [
            'hazardous_only' => 'hazardous only',
        ],
        'col' => [
            'items' => 'Items',
            'picked_up' => 'Pickup',
        ],
    ],

    'field' => [
        'site' => 'Site',
        'diary_entry' => 'Order',
        'picked_up_on' => 'Pickup date',
        'total_weight' => 'Total weight (kg)',
        'created' => 'Created',
        'cancelled_at' => 'Cancelled on',
        'cancel_reason' => 'Cancellation reason',
        'completed_at' => 'Completed on',
        'completed_by' => 'Completed by',
    ],

    'form' => [
        'title_create' => 'New disposal case',
        'title_edit' => 'Edit disposal case',
        'submit_create' => 'Create case',
        'group_assignment' => 'Customer & assignment',
        'group_pickup' => 'Pickup & details',
        'site' => 'Site (optional)',
        'site_none' => 'no site',
        'diary_entry' => 'Order/case file (optional)',
        'diary_entry_none' => 'no order reference',
    ],

    'show' => [
        'nav' => 'Disposal case',
        'title' => 'Disposal case :number',
        'section' => [
            'job' => 'Case',
            'blockers' => 'Completion check',
            'items' => 'Device list',
            'handovers' => 'Disposal contractor handovers',
            'signature' => 'Takeover confirmation',
            'record' => 'Customer record',
        ],
    ],

    'badge' => [
        'hazardous' => 'hazardous',
        'signed' => 'Takeover signed',
    ],

    'item' => [
        'title_create' => 'Add item',
        'title_edit' => 'Edit item',
        'group_device' => 'Device',
        'group_disposal' => 'Disposal & data media',
        'weight' => 'Weight (kg)',
        'condition_note' => 'Condition note',
        'avv_code' => 'Waste code (AVV/EWC)',
        'avv_hint' => 'Asterisk * = hazardous waste — the classification is derived automatically.',
        'has_data_storage' => 'Device contains data media',
        'note' => 'Note',
        'empty' => 'No device items — add devices via "Add item".',
        'col' => [
            'device' => 'Manufacturer/model',
            'weight' => 'Weight (kg)',
            'avv' => 'Waste code (AVV/EWC)',
            'data_storage' => 'Data media',
        ],
        'treatments_count' => '1 treatment|:count treatments',
        'treatment_missing' => 'Treatment missing',
    ],

    'treatment' => [
        'title_create' => 'Record data media treatment',
        'group_method' => 'Method & standard',
        'group_evidence' => 'Execution & evidence',
        'media_type' => 'Data medium type',
        'method' => 'Method',
        'din_category' => 'DIN 66399 material category',
        'security_level' => 'Security level (1–7)',
        'protection_class' => 'Protection class',
        'protection_class_none' => 'not specified',
        'protection_class_short' => 'Protection class :class',
        'treated_at' => 'Time',
        'performed_by' => 'Performed by',
        'evidence_reference' => 'Evidence/certificate reference',
        'please_select' => '-- please select --',
    ],

    'handover' => [
        'title_create' => 'Record disposal contractor handover',
        'group_proof' => 'Disposal contractor & proof',
        'group_attachment' => 'Document & note',
        'disposer' => 'Disposal contractor',
        'proof_type' => 'Proof type',
        'document_number' => 'Document number',
        'handed_over_on' => 'Handover date',
        'certificate_reference' => 'EfbV certificate reference',
        'proof_file' => 'Proof file (optional)',
        'proof_file_hint' => 'PDF, JPG or PNG — up to 10 MB. The proof is stored as a DMS document.',
        'note' => 'Note',
        'no_disposers' => 'No certified disposal contractor on file.',
        'create_disposer' => 'Create disposal contractor as external contact',
        'empty' => 'No handover to a disposal contractor recorded yet.',
        'col' => [
            'disposer' => 'Disposal contractor',
            'proof_type' => 'Proof type',
            'document_number' => 'Document number',
            'certificate' => 'EfbV reference',
            'document' => 'DMS document',
        ],
    ],

    'sign' => [
        'signer_name' => 'Name of the person taking over',
        'signed_at' => 'Signed on',
        'hash' => 'Checksum',
        'hint' => '"Confirm takeover" stores the signature in an audit-proof way.',
        'missing' => 'No takeover signature available.',
    ],

    'record' => [
        'released_hint' => 'The customer record is released in the customer portal.',
        'pending_hint' => 'The customer record is generated automatically when the case is completed.',
    ],

    'cancel' => [
        'title' => 'Cancel disposal case',
        'intro' => 'Cancellation is final and is logged in the chain of custody together with the reason.',
        'reason' => 'Reason',
    ],

    'action' => [
        'create' => 'New disposal case',
        'collect' => 'Record pickup',
        'start_treatment' => 'Start treatment',
        'hand_over' => 'Hand over to disposal contractor',
        'pdf_preview' => 'Record PDF (preview)',
        'add_item' => 'Add item',
        'add_treatment' => 'Record treatment',
        'add_handover' => 'Record handover',
        'sign' => 'Confirm takeover',
    ],

    'confirm' => [
        'complete' => 'Complete the case? The customer record is generated and released, and linked assets are decommissioned.',
        'delete_item' => 'Really remove this device item?',
        'delete_treatment' => 'Really remove this data media treatment?',
        'delete_handover' => 'Really remove this disposal contractor handover?',
    ],

    'pdf' => [
        'title' => 'Takeover and disposal record',
        'number' => 'Case number',
        'customer' => 'Customer',
        'picked_up_on' => 'Pickup date',
        'responsible' => 'Responsible',
        'status' => 'Status',
        'total_weight' => 'Total weight',
        'items' => 'Device list',
        'treatments' => 'Data protection and data media record (DIN 66399)',
        'handovers' => 'Disposal and whereabouts record',
        'confirmation' => 'Confirmation',
        'customer_signature' => 'Takeover by the customer',
        'not_signed' => 'Not signed.',
        'provider' => 'Service provider',
        'completed_at' => 'Completed on',
        'hazardous_suffix' => '(hazardous)',
        'col' => [
            'category' => 'Category',
            'device' => 'Manufacturer/model',
            'serial' => 'Serial number',
            'quantity' => 'Quantity',
            'weight' => 'Weight (kg)',
            'avv' => 'Waste code (AVV/EWC)',
            'media_type' => 'Data medium type',
            'method' => 'Method',
            'din' => 'DIN 66399',
            'protection_class' => 'Protection class',
            'treated_at' => 'Time',
            'performed_by' => 'Performed by',
            'evidence' => 'Evidence/certificate no.',
            'disposer' => 'Disposal contractor',
            'proof_type' => 'Proof type',
            'document_number' => 'Document number',
            'handed_over_on' => 'Date',
            'certificate' => 'EfbV certificate',
        ],
        'footer' => [
            'hash' => 'Checksum',
            'generated' => 'Generated on :at',
        ],
    ],
];
