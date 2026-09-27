<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : branch_profile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Branchenprofile: kundenspezifische Varianten (MVP-933).
return [
    'variant' => [
        'title' => 'Profile variant',
        'list' => 'Customer-specific variants',
        'create' => 'Create variant',
        'subtitle' => 'Overlay of “:base” · version :version',
        'empty' => 'No variants yet. A variant takes a branch profile, leaves out building blocks and adds its own.',
        'edit' => 'Edit',
        'save' => 'Save variant',
        'install' => 'Install',
        'force' => 'Update existing entries',
        'export' => 'Export as JSON',
        'delete' => 'Delete',
        'confirm_delete' => 'Delete the variant? Entries already installed are kept.',
        'installed' => 'Installed: variant :code, version :version.',
        'not_installed' => 'No variant of this base profile is installed yet.',
        'removals' => 'Leave out building blocks',
        'additions' => 'Additions',
        'field' => [
            'base_code' => 'Base profile',
            'code' => 'Code',
            'label' => 'Name',
            'description' => 'Description',
            'additions' => 'Profile excerpt (JSON)',
        ],
        'hint' => [
            'code' => 'Lower-case letters, digits and hyphens.',
            'removals' => 'Ticked building blocks of the base profile are not created on installation. Existing entries stay untouched.',
            'additions' => 'Same structure as a branch profile, e.g. {"tags_seed": ["#own"]}. Building blocks with the same code replace those of the base profile.',
        ],
        'section' => [
            'entry_type_defaults' => 'Default entry types',
            'modules_recommended' => 'Recommended modules',
            'classifications' => 'Classifications',
            'classification_requirements' => 'Required rules',
            'procedure_templates' => 'Procedure templates',
            'protocol_templates' => 'Protocol templates',
            'asset_categories' => 'Asset categories',
            'tags_seed' => 'Tags',
            'maintenance_plans_seed' => 'Maintenance plans',
            'sla_contracts_seed' => 'SLA contracts',
            'dataprotection_requirements_seed' => 'Data protection requirements',
            'contract_templates' => 'Contract templates',
            'training_suggestions' => 'Training suggestions',
            'room_requirement_templates_seed' => 'Room requirements',
            'qualifications_seed' => 'Qualifications',
            'custom_fields' => 'Custom fields',
            'cleaning_profiles_seed' => 'Cleaning profiles',
            'software_seed' => 'Software',
        ],
        'flash' => [
            'created' => 'Variant created.',
            'saved' => 'Variant saved.',
            'installed' => 'Variant “:label” installed.',
            'deleted' => 'Variant deleted.',
        ],
        'error' => [
            'json' => 'The additions must be a JSON object.',
        ],
    ],
];
