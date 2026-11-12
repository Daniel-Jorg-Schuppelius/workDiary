<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : classification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dataquality' => [
        'heading' => 'Data quality: required classifications missing',
        'missing' => ':domain missing',
    ],
    'error' => [
        'requirementUnmet' => 'Mandatory classifications missing: :domains',
        'requirementMin' => 'Mandatory classification “:domain”: at least :min entry/entries required, present: :actual.',
        'requirementMax' => 'Mandatory classification “:domain”: at most :max entry/entries allowed, present: :actual.',
        'referenced' => 'The value “:label” is still in use (:usage) and cannot be deleted. Retire it instead: it disappears from new selections, existing records keep it.',
    ],
    'action' => [
        'deactivate' => 'Retire',
        'deactivate_confirm' => 'Retire this value? It can no longer be selected, existing records keep it.',
    ],
    'flash' => [
        'deactivated' => '“:label” has been retired.',
    ],
    'reference' => [
        'time_entries' => 'Time entries',
        'claim_cases' => 'Claims',
        'products' => 'Articles',
        'classifiables' => 'Assignments on records',
        'import_value_mappings' => 'Import mappings',
        'assets' => 'Assets',
    ],
];
