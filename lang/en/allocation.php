<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : allocation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Split time',
    'entry_duration' => 'Entry duration',
    'hint' => 'Empty rows are ignored; clearing all rows removes the split. The sum of the shares must not exceed the duration.',
    'target' => 'Target',
    'minutes' => 'Minutes',
    'quantity' => 'Quantity',
    'comment' => 'Comment',
    'none_option' => '— no share —',
    'type' => [
        'task' => 'Tasks',
        'asset' => 'Assets',
        'project' => 'Projects',
        'cost_center' => 'Cost centers',
        'site' => 'Sites',
        'vehicle' => 'Vehicles',
        'activity_category' => 'Activities',
    ],
    'action' => [
        'split' => 'Split',
        'save' => 'Save split',
    ],
    'flash' => [
        'saved' => 'Split saved.',
    ],
    'error' => [
        'locked' => 'Entry is locked (:reason) — splitting not possible.',
        'invalid_target' => 'Invalid or foreign allocation target.',
        'minutes_min' => 'Each share needs at least one minute.',
        'sum_exceeds' => 'The sum of the shares (:sum min) exceeds the entry duration (:max min).',
    ],
    // Free tenant dimensions (MVP-514 P2)
    'dimensions' => [
        'nav' => 'Time dimensions',
        'title' => 'Free time dimensions',
        'intro' => 'Custom dimensions for time allocation (e.g. ERP orders) — only for targets without an existing WorkDiary model. The external ID anchors a future provider synchronization.',
        'new_type' => 'New dimension type',
        'code' => 'Code',
        'name' => 'Name',
        'create_type' => 'Create type',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'no_types' => 'No dimension types yet.',
        'no_values' => 'No values yet.',
        'external_id' => 'External ID',
        'validity' => 'Validity',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'create_value' => 'Create value',
        'delete_value' => 'Delete',
        'flash' => [
            'type_created' => 'Dimension type created.',
            'type_enabled' => 'Dimension type enabled.',
            'type_disabled' => 'Dimension type disabled.',
            'value_created' => 'Value created.',
            'value_deleted' => 'Value deleted.',
        ],
    ],
];
