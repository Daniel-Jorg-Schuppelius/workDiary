<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : flex.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'eligibility' => [
        'title' => 'Flex Eligibility for :name',
        'nav_title' => 'Flex Eligibility',
        'subtitle' => 'Periods during which :name participates in flex time tracking.',
        'current' => [
            'active' => 'Currently flex eligible',
            'inactive' => 'Currently not flex eligible',
        ],
        'table' => [
            'valid_from' => 'Valid from',
            'valid_to' => 'Valid until',
            'open' => 'open-ended',
            'note' => 'Note',
            'actions' => 'Actions',
        ],
        'form' => [
            'add_title' => 'Add new period',
            'valid_from' => 'Valid from',
            'valid_to' => 'Valid until (empty = open-ended)',
            'note' => 'Note (optional)',
            'submit' => 'Create period',
            'end_today' => 'End today',
            'end_submit' => 'End',
        ],
        'flash' => [
            'saved' => 'Flex period saved.',
            'deleted' => 'Flex period deleted.',
        ],
        'empty' => ':name has no flex periods on record — they do not participate in flex time.',
        'confirm_delete' => 'Really delete this period? Balance calculations will be re-run.',
    ],
];
