<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : print.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Print products / copy shop (MVP-459, druck-kopiershop branch profile).
return [
    'document_title' => 'Print data :number',

    'nav' => [
        'section' => 'Print & copy shop',
    ],

    'orders' => [
        'title' => 'Print orders',
        'subtitle' => 'Data intake, preflight, print approval, production, quality control and hand-over — reproducibly attached to the manufacturing order.',
        'detail_title' => 'Print order',
        'empty' => 'No print orders in the selected period — new orders are created via the dialog.',
        'kpi' => [
            'open' => 'Open print orders',
        ],
        'action' => [
            'create' => 'New print order',
            'create_submit' => 'Create order',
            'manufacturing' => 'Manufacturing order',
            'bind_file' => 'Bind file',
            'run_preflight' => 'Run preflight',
            'override' => 'Override with reason',
            'manual_preflight' => 'Save manual findings',
            'approve' => 'Grant print approval',
            'start_production' => 'Start production',
            'resume_production' => 'Resume production',
            'quality_check' => 'Record QC',
            'issue' => 'Hand over',
            'cancel' => 'Cancel',
        ],
    ],

    'section' => [
        'order' => 'Order',
        'file' => 'Production file & preflight',
        'approval' => 'Print approval & snapshot',
        'production' => 'Production, QC & hand-over',
        'claims' => 'Claims',
    ],

    'field' => [
        'article' => 'Article/print product',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'customer_optional' => 'Customer (optional)',
        'walk_in' => 'Walk-in customer (data-minimal)',
        'due_at' => 'Due date',
        'output_kind' => 'Output kind',
        'files_retain_until' => 'Production file retention until',
        'preflight' => 'Preflight',
        'file' => 'File',
        'file_hash' => 'Checksum (SHA-256)',
        'file_bound_at' => 'Bound at',
        'preflight_provider' => 'Check tool',
        'preflight_at' => 'Checked at',
        'override_reason' => 'Override reason',
        'manual_errors' => 'Errors (one per line)',
        'manual_warnings' => 'Warnings (one per line)',
        'approved_by' => 'Approved by',
        'approved_at' => 'Approved at',
        'approved_file_hash' => 'Approved checksum',
        'machine' => 'Machine',
        'without_machine' => 'without machine binding',
        'production_started_at' => 'Production start',
        'qc_status' => 'QC result',
        'qc_by' => 'QC by',
        'qc_note' => 'QC note',
        'issued_at' => 'Handed over at',
        'handover_name' => 'Handed over to',
        'handover_note' => 'Hand-over note',
        'shipment' => 'Shipment',
        'reason' => 'Reason',
        'good_total' => 'Good quantity',
        'scrap_total' => 'Waste',
        'cancel_reason' => 'Cancellation reason',
    ],

    'snapshot' => [
        'final_format' => 'Final format',
        'pages' => 'Pages',
        'orientation' => 'Orientation',
        'bleed_mm' => 'Bleed (mm)',
        'safety_mm' => 'Safety margin (mm)',
        'color_mode' => 'Colour mode',
        'color_profile' => 'Colour profile',
        'spot_colors' => 'Spot colours',
        'material' => 'Material/substrate',
        'grammage' => 'Grammage',
        'quantity' => 'Quantity',
        'due_date' => 'Due date',
        'finishing' => 'Finishing',
    ],

    'badge' => [
        'approval_stale' => 'File changed — approval invalid',
        'file_purged' => 'File removed after retention period',
    ],

    'qc' => [
        'passed' => 'Released',
        'rework' => 'Rework',
        'blocked' => 'Blocked',
    ],

    'hint' => [
        'retention' => 'When the period expires only the customer file is removed — order, snapshot and checksum remain as commercial evidence.',
        'no_snapshot' => 'No print approval yet — parameters are frozen as an immutable snapshot upon approval.',
        'counter_minimal' => 'Counter sale: no personal data required.',
        'claim_reference' => 'The case is linked to the print order — approved file, production snapshot and QC result stay referenceable through it.',
    ],

    'flash' => [
        'created' => 'Print order created.',
        'file_bound' => 'Production file bound (checksum stored).',
        'preflight_recorded' => 'Preflight findings stored.',
        'preflight_overridden' => 'Preflight overridden with reason.',
        'approved' => 'Print approval granted — snapshot frozen.',
        'production_started' => 'Production running.',
        'quality_checked' => 'Quality control recorded.',
        'issued' => 'Order handed over.',
        'cancelled' => 'Order cancelled.',
        'claim_opened' => 'Claim :number created.',
    ],

    'preflight' => [
        'file_missing' => 'Production file cannot be found in storage.',
        'file_empty' => 'The file is empty (0 bytes).',
        'mime_unexpected' => 'Unexpected file type ":mime" — verify for printing.',
        'pdf_header_invalid' => 'The file is declared as PDF but has no valid PDF header.',
    ],

    'error' => [
        'customer_approval_status' => 'Customer approval can only be requested during the data check.',
        'customer_approval_not_pending' => 'There is no open approval request.',
        'customer_approval_stale' => 'The print file has changed in the meantime — please wait for the new approval request.',
        'customer_decline_reason_required' => 'Please give a reason for the rejection.',
        'customer_approval_missing' => 'The customer approval for the bound print file is missing.',
        'customer_parameter_mismatch' => ':parameter differs from the customer approval (approved: :approved). Please request approval again.',
        'order_already_specialized' => 'A print order already exists for this manufacturing order (1:1).',
        'order_closed' => 'The print order is closed — the file can no longer be changed.',
        'document_mismatch' => 'Document/version do not match or do not belong to this organisation.',
        'file_required' => 'Bind a production file first.',
        'provider_unsupported' => 'The check tool does not support this file type.',
        'override_only_failed' => 'Only blocking preflight errors can be overridden.',
        'override_reason_required' => 'The override requires a reason.',
        'preflight_blocks_approval' => 'Preflight pending or failed — approval only after checking or a reasoned override.',
        'parameter_required' => 'Required parameter missing: :parameter.',
        'approval_stale' => 'The file was changed after approval — the order requires checking/approval again.',
        'machine_foreign' => 'The machine does not belong to this organisation.',
        'machine_inspection_overdue' => 'Machine with overdue mandatory inspection/calibration — production start not permitted.',
        'invalid_transition' => 'Invalid status transition.',
        'invalid_transition_detail' => 'Invalid status transition: :from → :to.',
        'shipment_required' => 'Shipping hand-over requires an existing shipment.',
        'handover_required' => 'Pickup requires a hand-over record (name).',
        'cancel_reason_required' => 'Cancellation requires a reason.',
        'file_missing_storage' => 'The file version does not exist in storage.',
    ],

    // Reklamation am Druckauftrag (Issue #75).
    'claim' => [
        'title' => 'Claim for print order :number',
        'none' => 'No claims for this order.',
        'description' => 'Description',
        'affected_quantity' => 'Affected quantity',
        'affected_quantity_note' => 'Affected quantity: :quantity',
        'open' => 'Open claim',
    ],
    'intake' => [
        'target_label' => 'Print order :number',
        'version_note' => 'Taken over from customer intake',
        'article_invalid' => 'Please select a manufacturable article.',
        'file_invalid' => 'The selected file does not belong to the intake.',
        'production_file' => 'Production file',
        'production_file_hint' => 'Further files remain attached to the intake. It can also be set later on the print order.',
        'production_file_later' => 'Set later',
        'panel_title' => 'Print order',
        'no_production_file' => 'No production file yet',
        'customer_approval' => 'Customer approval',
        'open_order' => 'Open print order',
        'origin_title' => 'From customer intake',
        'quote' => 'Quote :number (version :version)',
        'intake_files' => 'Intake files',
        'bind_file' => 'Set as production file',
        'bind_confirm' => 'Bind this file as production file? Checks and approvals of another file expire.',
        'request_approval' => 'Request customer approval',
        'portal_title' => 'Print approval',
        'portal_waiting' => 'We are checking your print data. As soon as it is ready, we will ask for your approval here.',
        'portal_confirm' => 'With your approval you confirm exactly this file and these parameters. Printing starts only after our internal approval.',
        'version' => 'Version :version',
        'checksum' => 'Checksum (SHA-256)',
        'download_file' => 'Download file',
        'decline_reason' => 'Reason (only when rejecting)',
        'approve' => 'Approve print data',
        'decline' => 'Reject',
        'flash' => [
            'approval_requested' => 'Customer approval requested.',
            'approved' => 'Thank you — your print approval has been documented.',
            'declined' => 'Your rejection has been sent.',
        ],
        'approval' => [
            'approved' => 'Approved on :date',
            'pending' => 'Requested on :date',
            'declined' => 'Rejected on :date',
            'none' => 'Not yet requested',
        ],
        'stage' => [
            'approval_pending' => 'Print approval required',
            'approval_declined' => 'Print data is being revised',
            'data_check' => 'Print data is being checked',
            'approved' => 'Approved — production scheduled',
            'in_production' => 'In production',
            'ready_pickup' => 'Ready for pickup',
            'ready_shipping' => 'Ready for shipping',
            'ready_counter' => 'Ready for pickup',
            'issued' => 'Issued',
            'cancelled' => 'Cancelled',
        ],
        'next_step' => [
            'approve' => 'Please review the file and parameters and approve the print data.',
        ],
    ],
];
