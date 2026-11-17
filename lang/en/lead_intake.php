<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lead_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Intake file with check packages (Feature 200, MVP-1641–1644, MVP-1675).
return [
    'title' => 'Intake file',
    'title_for' => 'Intake file: :name',
    'index_title' => 'Intakes',
    'index_subtitle' => 'Intake files of leads, from the enquiry to setting up the customer.',
    'back_to_lead' => 'To the lead',
    'revision' => 'Version :revision',
    'superseded' => 'Superseded',
    'stages' => 'Stages',
    'current_stage' => 'Current stage',

    'action' => [
        'open' => 'Open intake',
        'decide' => 'Decide',
        'refresh' => 'Update',
        'pdf' => 'PDF',
        'archive' => 'File as document',
        'move_to' => 'Next: :stage',
        'save' => 'Save',
        'confirm_second_review' => 'Confirm acceptance',
        'return_second_review' => 'Return',
    ],
    'confirm' => [
        'refresh' => 'Create a new version of the file? The answers are carried over and can be checked and completed.',
        'archive' => 'File the PDF without identity and health details as a document?',
        'second_review' => 'Confirm the acceptance as second person?',
    ],
    'hint' => [
        'restricted' => ':count details of this file need a separate permission and are hidden.',
        'restricted_answered' => ':kind: :count filled in.',
        'no_packages' => 'No check packages enabled. Files then only contain the interview form; leads can be converted directly as before.',
    ],

    'form' => [
        'section_base' => 'Enquiry and interview',
        'request' => 'Request',
        'services' => 'Requested services',
        'services_help' => 'Becomes the service scope of the new customer on conversion.',
        'start_on' => 'Desired start',
        'interview_notes' => 'Interview notes',
        'section_traits' => 'Deadline attributes: :rule_set',
        'traits_help' => 'Become deadline attributes of the new customer on conversion.',
        'section_documents' => 'Documents: :package',
        'documents_help' => 'Tick as soon as the document is available; file it below under documents.',
    ],

    'field' => [
        'stage' => 'Stage',
        'decision' => 'Decision',
        'decision_reason' => 'Reason',
        'decided_at' => 'Decided on',
        'decided_by' => 'Decided by',
        'second_review' => 'Second review',
        'protocol' => 'Record',
        'packages' => 'Check packages',
        'refresh_due_on' => 'Update due',
        'retention_until' => 'Retain until',
        'customer' => 'Customer',
        'relationship_ended_on' => 'End of business relationship',
    ],

    'documents' => [
        'title' => 'Documents',
        'upload' => 'File',
        'upload_for' => 'File for “:document”',
    ],

    'decision' => [
        'title' => 'Decision',
        'legend' => 'Accept or reject the intake',
        'open' => 'Not decided yet.',
        'missing' => 'Still missing before acceptance: :count',
        'blocks_conversion' => 'A check package requiring a decision is active: the lead only becomes a customer after acceptance.',
        'reason_hint' => 'The reason is kept in the file and in the PDF.',
        'second_review_pending' => 'Accepted — the second review by another person is pending.',
        'return_reason' => 'Reason for returning',
    ],

    'packages' => [
        'title' => 'Check packages and deadlines',
        'none' => 'None',
    ],
    'versions' => [
        'title' => 'Versions',
    ],
    'retention' => [
        'open' => 'runs from the end of the business relationship',
    ],

    'settings' => [
        'title' => 'Intake settings',
        'submit' => 'Save',
        'packages' => 'Check packages',
        'none_available' => 'No check package available.',
        'packages_hint' => 'Enabled packages apply to newly opened files. A package requiring a decision blocks conversion without acceptance.',
        'refresh' => 'Update per AML risk level (months)',
        'refresh_for' => 'Risk level :level',
        'refresh_hint' => 'Default: yearly. The period starts with the acceptance.',
        'second_review' => 'Second review',
        'second_review_label' => 'Have the acceptance confirmed by a second person',
        'second_review_hint' => 'Default: off. The second person needs the decision permission and must not have accepted themselves.',
    ],

    'relationship' => [
        'title' => 'End of business relationship',
        'submit' => 'Save',
        'legend' => 'Business relationship',
        'hint' => 'From the end of this calendar year the retention of the intake files runs (AML: five years, § 8 (4) GwG). Leave empty while it continues.',
        'ongoing' => 'ongoing',
    ],

    'cockpit' => [
        'none' => 'No intake file yet. Active check packages: :packages',
        'refresh_due' => 'Update :date',
        'retention' => 'retain until :date',
    ],

    'filter' => [
        'status' => 'Selection',
        'status_open' => 'Open intakes',
        'status_refresh' => 'Update due',
        'status_all' => 'All',
        'stage' => 'Stage',
        'all_stages' => 'All stages',
    ],
    'empty' => [
        'title' => 'No intakes',
        'message' => 'Intake files are opened on the lead.',
    ],
    'column' => [
        'lead' => 'Lead',
        'stage' => 'Stage',
        'packages' => 'Check packages',
        'decision' => 'Decision',
        'refresh_due_on' => 'Update',
        'updated_at' => 'Changed',
    ],

    'widget' => [
        'title' => 'Intakes',
        'description' => 'Open intake files per stage, from the enquiry to setup.',
        'open_list' => 'All intakes',
        'empty' => 'No intake is open right now.',
    ],

    'flash' => [
        'opened' => 'Intake file opened.',
        'saved' => 'Form saved.',
        'stage' => 'Stage: :stage',
        'decided' => 'Decided: :decision',
        'second_review_confirmed' => 'Acceptance confirmed.',
        'second_review_returned' => 'Acceptance returned — the form is open again.',
        'document_added' => 'Document filed.',
        'refreshed' => 'Version :revision created.',
        'archived' => 'Filed as document: :file',
        'settings_saved' => 'Intake settings saved.',
        'relationship_saved' => 'Saved; retention of :count intake files recalculated.',
    ],

    'error' => [
        'anonymized' => 'The lead has been anonymised.',
        'stage_by_decision' => 'Setup, “set up” and “rejected” follow from the decision or the conversion.',
        'closed' => 'The file has been decided or superseded by a newer version.',
        'reason_required' => 'Please give a reason for the decision.',
        'no_second_review' => 'This acceptance is not waiting for a second review.',
        'same_person' => 'The second review is given by a person other than the one who accepted.',
        'refresh_needs_acceptance' => 'Only the latest accepted version can be updated.',
        'unknown_document' => 'This document does not belong to the file.',
        'restricted' => 'The separate permission for this document is missing.',
        'no_carrier' => 'Neither customer nor lead found for filing.',
        'restricted_missing' => 'Detail requiring a separate permission (:right) is missing.',
    ],

    'block' => [
        'no_intake' => 'A check package requiring a decision is active: please open the intake file and accept the intake first.',
        'not_accepted' => 'The intake has not been accepted yet — only then does the lead become a customer.',
        'second_review' => 'The acceptance is waiting for the second review.',
        'rejected' => 'The intake was rejected; the lead will not be converted.',
    ],

    'transfer' => [
        'service_note' => 'Taken over from the intake file.',
        'traits_incomplete' => 'Deadline attributes “:rule_set” from the intake file are incomplete and were not taken over — please complete them on the customer.',
    ],

    'notification' => [
        'refresh' => [
            'title' => ':count intake file(s) to update',
            'message' => 'Due from :date: :names',
        ],
    ],

    'work' => [
        'decide' => 'Decide intake: :name',
        'second_review' => 'Second review: :name',
        'refresh' => 'Update intake: :name',
    ],

    'pdf' => [
        'file_prefix' => 'intake',
        'customer' => 'Customer',
        'restricted' => 'restricted (:kind)',
    ],

    'vat_id' => [
        'invalid' => 'The VAT ID is formally invalid (structure or check digit).',
    ],

    'dsar' => [
        'title' => 'Intake files',
        'list_title' => 'Intake file, version :revision',
        'column' => [
            'topic' => 'Detail',
            'value' => 'Content',
        ],
        'legal_basis' => 'Legal basis of retention',
        'legal_basis_value' => 'Art. 6(1)(c) GDPR in conjunction with :basis',
        'restricted' => 'present — output only with the permission “:right”',
    ],

    'demo' => [
        'request' => 'Ongoing bookkeeping, payroll for three employees and annual accounts from next month.',
        'notes' => 'First meeting at the office; documents from the previous adviser follow by upload.',
        'purpose' => 'Tax advice: bookkeeping, payroll and annual accounts for the partnership.',
    ],

    'gwg' => [
        'label' => 'Anti-Money Laundering Act (GwG)',
        'protocol_title' => 'Identification and risk record: :name',
        'protocol_description' => 'From the intake file (version :revision). Risk level: :risk. Reason: :reason',
        'document' => [
            'id_copy' => 'Copy of the identity document or register extract (§ 8 (2) GwG)',
        ],
        'section' => [
            'identification' => 'AML: Identification of the contracting party (§ 11 GwG)',
            'verification' => 'AML: Verification of identity (§ 12 GwG)',
            'beneficial_owners' => 'AML: Beneficial owners (§ 3 GwG)',
            'pep' => 'AML: Politically exposed person (§ 15 (3) no. 1 GwG)',
            'purpose' => 'AML: Purpose and nature of the business relationship (§ 10 (1) no. 3 GwG)',
            'risk' => 'AML: Risk assessment (§§ 14, 15 GwG)',
        ],
        'field' => [
            'party_kind' => 'The contracting party is',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'birth_place' => 'Place of birth',
            'birth_date' => 'Date of birth',
            'nationality' => 'Nationality',
            'home_address' => 'Residential address',
            'company_name' => 'Company, name or designation',
            'legal_form' => 'Legal form',
            'register_number' => 'Register number',
            'seat_address' => 'Address of registered office or principal place of business',
            'representatives' => 'Members of the representative body or legal representatives',
            'verification_method' => 'Verified by',
            'id_document_kind' => 'Type of document',
            'id_document_number' => 'Document number',
            'id_issuer' => 'Issuing authority',
            'verified_on' => 'Verified on',
            'beneficial_owners' => 'Beneficial owners',
            'transparency_register_checked' => 'Transparency register inspected',
            'transparency_register_on' => 'Transparency register: retrieved on',
            'transparency_register_reference' => 'Transparency register: extract or reference',
            'pep_status' => 'Politically exposed person',
            'pep_details' => 'Details of the position or relationship',
            'purpose' => 'Purpose and intended nature of the business relationship',
            'suspicion_checked' => 'Indications of money laundering or terrorist financing checked',
            'lower_risk_factors' => 'Factors of lower risk (Annex 1 GwG)',
            'higher_risk_factors' => 'Factors of higher risk (Annex 2 GwG)',
            'risk_level' => 'Risk level',
            'risk_reason' => 'Reason for the risk level',
        ],
        'help' => [
            'party_kind' => 'Natural person: § 11 (4) no. 1 GwG; legal person or partnership: no. 2.',
            'verification_method' => 'With an ID or register extract: record type, number and issuing authority and file a copy (§ 8 (2) GwG).',
            'id_document_number' => 'Stored encrypted and only visible with the permission for identity details.',
            'beneficial_owners' => 'Name, type and extent of the interest or control; for a natural person acting on their own behalf, themselves.',
            'transparency_register_checked' => 'Proof of registration or extract from the transparency register (§ 11 (5), § 12 (3) GwG).',
            'pep_status' => 'Enhanced due diligence applies to a politically exposed person — the risk level is then high.',
            'suspicion_checked' => 'The file only records the check; no report to the FIU is made from the app.',
            'lower_risk_factors' => 'Non-exhaustive factors according to Annex 1 GwG.',
            'higher_risk_factors' => 'Non-exhaustive factors according to Annex 2 GwG.',
            'risk_level' => 'Low: simplified due diligence (§ 14), normal: general (§ 10), high: enhanced (§ 15). Determines the update period.',
            'risk_reason' => 'Mandatory for low and high (§ 8 (1) sentence 1 no. 2 GwG).',
        ],
        'option' => [
            'party_kind' => [
                'natural' => 'Natural person',
                'legal' => 'Legal person or partnership',
            ],
            'verification_method' => [
                'id_document' => 'Official photo ID (identity card, passport)',
                'eid' => 'Electronic proof of identity (eID)',
                'qes' => 'Qualified electronic signature',
                'register_extract' => 'Extract from the commercial or cooperative register',
                'formation_documents' => 'Formation documents',
                'own_register_check' => 'Own documented inspection of the register',
            ],
            'pep_status' => [
                'no' => 'No',
                'pep' => 'Yes, politically exposed',
                'family' => 'Family member of a politically exposed person',
                'associate' => 'Known close associate',
            ],
            'lower_risk_factors' => [
                'listed_company' => 'Listed company subject to disclosure requirements',
                'public_body' => 'Public administration or public enterprise',
                'low_risk_area' => 'Seat or residence in a lower-risk area',
                'low_risk_product' => 'Low-risk service',
            ],
            'higher_risk_factors' => [
                'unusual_circumstances' => 'Unusual circumstances of the business relationship',
                'high_risk_area' => 'Seat or residence in a high-risk area',
                'asset_holding_vehicle' => 'Personal asset-holding vehicle',
                'nominee_shareholders' => 'Nominee shareholders or bearer shares',
                'cash_intensive' => 'Cash-intensive business',
                'complex_structure' => 'Unusually complex ownership structure',
                'no_personal_contact' => 'No personal contact without safeguards',
                'unknown_third_party_payments' => 'Payments from unknown or unassociated third parties',
                'high_risk_goods' => 'Trade in oil, arms, precious metals, tobacco, cultural goods or protected species',
            ],
        ],
        'error' => [
            'pep_requires_high' => 'Enhanced due diligence applies to a politically exposed person: choose risk level “high” (§ 15 (3) no. 1 GwG).',
            'copy_missing' => 'The copy of the document is missing (§ 8 (2) GwG).',
        ],
    ],
];
