<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fields.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Feldschema-Baustein (MVP-866): Validierung der Definitionen, Anzeigewerte.
return [
    'validation' => [
        'invalid_row' => 'Field definition in row :row is invalid.',
        'label_required' => 'Field :row needs a label (max. 160 characters).',
        'unknown_type' => 'Field :row has an unknown field type.',
        'invalid_key' => 'Field key ":key" is invalid (lowercase letters, digits, underscores).',
        'duplicate_key' => 'Field key ":key" is used twice.',
        'select_needs_options' => 'Choice field ":label" needs at least one option.',
        'fields_required' => 'At least one field is required.',
        'too_many_fields' => 'At most :max fields.',
        'range_invalid' => 'Field ":label": minimum must not exceed maximum.',
        'condition_unknown_field' => 'Condition of field ":label" references an unknown field ":field".',
        'condition_cycle' => 'Conditions form a cycle (field ":field" indirectly depends on itself).',
    ],
    'value' => [
        'yes' => 'Yes',
        'no' => 'No',
        'signed' => 'Signed',
        'attachments' => '{1} :count attachment|[2,*] :count attachments',
    ],
    'action' => [
        'clear_signature' => 'Clear signature',
    ],
    'custom' => [
        'listed' => 'Show in list',
        'title' => 'Custom fields',
        'legend' => 'Additional fields',
        'subject' => 'Subject',
        'fields' => 'Fields',
        'status' => 'Status',
        'active' => 'Active',
        'inactive' => 'Deactivated',
        'none' => 'No fields defined yet.',
        'edit' => 'Edit fields',
        'save' => 'Save',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'values_count' => ':count records with values',
        'version' => 'Schema version :version',
        'saved' => 'Custom fields saved.',
        'toggled' => 'Status changed.',
        'intro' => 'One field list per subject; fields appear in the form, on the detail page and in exports. Deactivate instead of deleting once values exist.',
        'validation' => [
            'too_many_listed' => 'At most :max fields can appear in the list.',
            'unknown_subject' => 'This subject has no custom fields.',
            'type_not_allowed' => 'Field ":label": file, photo and signature fields are not available here.',
        ],
    ],
];
