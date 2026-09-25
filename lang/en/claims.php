<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Notable patterns (serial defects, lots)',
        'hint' => 'Groups with at least :threshold claims in the period. A hint, not a decision.',
        'none' => 'No notable patterns in the period.',
        'rule_label' => 'Rule',
        'group' => 'Group',
        'cases' => 'Cases',
        'count' => 'Count',
        'rule' => [
            'lot' => 'Lot',
            'article_defect' => 'Article × defect type',
            'article_cause' => 'Article × cause',
            'supplier_defect' => 'Supplier × defect type',
            'entry_type_cause' => 'Order type × cause',
        ],
        'notify_title' => 'Notable claim pattern: :label',
        'notify_message' => ':count claims in :days days (:rule).',
    ],
];
