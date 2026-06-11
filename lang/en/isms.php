<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : isms.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'section' => 'ISMS',
        'risks' => 'Risk register',
        'controls' => 'Control catalogue',
        'soa' => 'SoA',
    ],

    'subtitle' => [
        'risks' => 'Identify, assess (5×5) and treat information security risks.',
        'controls' => 'Maintain controls and document the SoA statement per control.',
    ],

    'field' => [
        'risk_no' => 'No.',
        'title' => 'Title',
        'description' => 'Description',
        'category' => 'Category',
        'asset_ref' => 'Reference (system/process/site)',
        'threat' => 'Threat',
        'likelihood' => 'Likelihood',
        'impact' => 'Impact',
        'score' => 'Score',
        'treatment' => 'Treatment',
        'status' => 'Status',
        'owner' => 'Owner',
        'review_due_on' => 'Review due',
        'controls' => 'Linked controls',
        'code' => 'Code',
        'source' => 'Source',
        'applicable' => 'Applicable',
        'justification' => 'Justification',
        'implementation_status' => 'Implementation status',
        'evidence_note' => 'Evidence note',
        'risks' => 'Linked risks',
    ],

    'group' => [
        'risk' => 'Risk',
        'assessment' => 'Assessment & treatment',
        'control' => 'Control',
        'soa' => 'Statement of Applicability',
    ],

    'action' => [
        'create_risk' => 'Add risk',
        'edit_risk' => 'Edit risk',
        'create_control' => 'Add control',
        'edit_control' => 'Edit control',
        'edit' => 'Edit',
        'save' => 'Save',
        'delete' => 'Delete',
        'transition' => 'Change status',
        'import_catalog' => 'Load Annex A catalogue',
        'back' => 'Back',
        'print' => 'Print / save PDF',
    ],

    'filter' => [
        'all' => 'All',
        'sort' => 'Sort',
        'sort_score' => 'Highest score first',
        'sort_review' => 'Review date',
        'sort_newest' => 'Newest first',
        'applicable_yes' => 'Applicable',
        'applicable_no' => 'Not applicable',
    ],

    'scale' => [
        'likelihood' => [
            1 => 'very rare',
            2 => 'rare',
            3 => 'possible',
            4 => 'likely',
            5 => 'very likely',
        ],
        'impact' => [
            1 => 'negligible',
            2 => 'minor',
            3 => 'noticeable',
            4 => 'severe',
            5 => 'critical',
        ],
    ],

    'matrix' => [
        'title' => 'Risk matrix (open risks)',
        'cell' => 'Likelihood :likelihood × impact :impact — :count risk(s)',
        'axes' => 'Rows: likelihood (1–5) · Columns: impact (1–5)',
        'legend' => 'Legend',
        'low' => 'Low (score ≤ 6)',
        'medium' => 'Medium (score 7–12)',
        'high' => 'High (score > 12)',
        'review_due' => '{1} 1 review due|[2,*] :count reviews due',
    ],

    'hint' => [
        'asset_ref' => 'e.g. ERP system, server room, data centre …',
        'threat' => 'Which threat/vulnerability is the cause?',
        'controls' => 'Multiple selection (hold Ctrl/Cmd)',
        'no_controls_yet' => 'No controls yet — load the Annex A catalogue first or add your own controls.',
        'code' => 'e.g. M-01 (custom control)',
        'justification' => 'required when not applicable',
        'evidence_note' => 'Reference to evidence/document',
    ],

    'flash' => [
        'risk_created' => 'Risk has been added.',
        'risk_updated' => 'Risk has been updated.',
        'risk_transitioned' => 'Risk status has been changed.',
        'risk_deleted' => 'Risk has been deleted.',
        'control_created' => 'Control has been added.',
        'control_updated' => 'Control has been updated.',
        'control_deleted' => 'Control has been deleted.',
        'catalog_imported' => 'Annex A catalogue loaded (:count new controls).',
    ],

    'error' => [
        'invalid_transition' => 'Status change from ":from" to ":to" is not allowed.',
        'justification_required' => 'A SoA justification is required for controls that are not applicable.',
    ],

    'soa' => [
        'document_title' => 'Statement of Applicability',
        'heading' => 'Statement of Applicability (SoA)',
        'generated_at' => 'As of',
        'control_count' => ':count controls',
        'yes' => 'Yes',
        'no' => 'No',
        'disclaimer' => 'Reference: ISO/IEC 27001:2022 Annex A (codes and own short titles only — no standard texts). Conformity assessment is performed by an independent certification body.',
    ],

    'empty_risks' => 'No risks recorded yet.',
    'empty_risks_title' => 'No risks found',
    'empty_controls' => 'No controls yet.',
    'empty_controls_title' => 'No controls found',
    'empty_controls_hint_catalog' => 'No controls yet — use "Load Annex A catalogue" to import the ISO/IEC 27001 reference catalogue (93 controls).',
    'empty_controls_linked' => 'No controls linked.',
    'empty_filtered' => 'No entries found for the current filters.',
    'confirm_delete_risk' => 'Really delete this risk?',
    'confirm_delete_control' => 'Really delete this control? Links to risks will be removed.',
    'confirm_import_catalog' => 'Load the ISO/IEC 27001:2022 Annex A reference catalogue (93 controls, code + short title only) into this organisation? Existing controls remain unchanged.',
];
