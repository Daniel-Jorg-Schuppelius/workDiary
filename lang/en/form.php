<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : form.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'templates' => 'Form templates',
        'template' => 'Template',
        'submissions' => 'Forms',
        'submission' => 'Submitted form',
        'values' => 'Entries',
        'panel' => 'Forms',
    ],

    'subtitle' => [
        'templates' => 'Maintain configurable forms (reports, checklists) without code.',
        'submissions' => 'Submitted forms — version-safe via the field definition snapshot.',
    ],

    'field' => [
        'name' => 'Name',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'target_entry_type' => 'Assignment: entry type',
        'target_customer' => 'Assignment: customer',
        'description' => 'Description',
        'status' => 'Status',
        'fields' => 'Fields',
        'submissions' => 'Submitted',
        'creator' => 'Created by',
        'template' => 'Template',
        'subject' => 'Reference',
        'submitted_by' => 'Submitted by',
        'submitted_at' => 'Submitted at',
        'field_label' => 'Field label',
        'field_type' => 'Field type',
        'field_required' => 'Required',
        'field_options' => 'Options',
        'field_help' => 'Help text',
        'field_unit' => 'Unit',
        'field_range' => 'Value range',
        'field_min' => 'Min',
        'field_max' => 'Max',
    ],

    'action' => [
        'create_template' => 'Create template',
        'edit' => 'Edit',
        'save' => 'Save',
        'activate' => 'Activate',
        'archive' => 'Archive',
        'delete' => 'Delete',
        'add_field' => 'Add field',
        'remove_field' => 'Remove field',
        'fill' => 'Fill out form',
        'submit' => 'Submit',
        'show' => 'View',
        'print' => 'Print',
        'download_pdf' => 'Download PDF',
        'clear_signature' => 'Clear signature',
        'back' => 'Back',
    ],

    'filter' => [
        'all' => 'All',
        'search' => 'Search',
        'search_placeholder' => 'Search template name',
        'period' => 'Period',
    ],

    'hint' => [
        'options' => 'Comma-separated, e.g. good, fair, poor',
        'unit' => 'e.g. kWh, °C, pcs',
    ],

    'subject_kind' => [
        'diary' => 'Order',
        'customer' => 'Customer',
        'asset' => 'Asset',
        'project' => 'Project',
    ],

    'value' => [
        'yes' => 'Yes',
        'no' => 'No',
        'signed' => 'Signed',
    ],

    'condition' => [
        'legend' => 'Visible when',
        'always' => '— always visible —',
        'value_placeholder' => 'Comparison value',
        'op' => [
            'eq' => 'equals',
            'ne' => 'not equal',
            'in' => 'one of (comma)',
            'filled' => 'filled in',
        ],
    ],

    // Offline erfasste Anhänge (Audit 2026-08, W4.1).

    'attachment' => ['pending' => 'Upload pending'],

    'validation' => [

        'no_upload_field' => 'There is no file/photo field with this key in the form.',
        'template_not_active' => 'This template is not active and cannot be filled out.',
    ],

    'flash' => [
        'template_created' => 'Template created.',
        'template_updated' => 'Template updated.',
        'template_activated' => 'Template activated.',
        'template_archived' => 'Template archived.',
        'template_deleted' => 'Template deleted.',
        'submitted' => 'Form saved.',
    ],

    'empty_templates_title' => 'No templates found',
    'empty_templates' => 'No form templates yet.',
    'empty_submissions_title' => 'No forms found',
    'empty_submissions' => 'No submitted forms yet.',
    'empty_filtered' => 'No entries found for the current filters.',
    'empty_panel' => 'No forms submitted for this record yet.',
    'confirm_archive' => 'Really archive this template? It will disappear from the fill-out selection.',
    'confirm_delete' => 'Really delete this template? Submitted forms are kept.',
];
