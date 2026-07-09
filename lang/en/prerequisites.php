<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prerequisites.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'blocked' => [
        'missing_required' => 'Prerequisite missing',
        'missing_optional' => 'Notice',
        'not_licensed' => 'Not licensed',
        'not_allowed' => 'No permission',
        'provider_unsupported' => 'Not supported by provider',
    ],
    'contact_role' => 'Please contact: :role',
    'warehouses' => [
        'missing' => 'Counting and posting require at least one warehouse.',
        'cta' => 'Manage warehouses',
    ],
    'dispatch' => [
        'cta' => "Go to the order's dispatch panel",
    ],
    'mappings' => [
        'hint' => 'Mappings are created automatically during import or when resolving inbox items (plugin sync and CSV import).',
        'cta' => 'Go to the integration inbox',
    ],
    'shift_types' => [
        'missing' => 'No shift types have been created yet — shifts can only be planned in a limited way without a type.',
        'cta' => 'Create shift types',
        'dialog_hint' => 'No shift types available yet. The shift is saved without a type; administrators manage shift types via "Shift types" in the schedule.',
    ],
];
