<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'draft' => [
        'error' => [
            'lexoffice' => 'Lexoffice is not enabled for this organization or has no API key.',
        ],
        'introduction' => 'Licences and subscriptions, :count line items — periods and end customers per line.',
        'note' => 'Lexoffice draft :id of :date (:user)',
        'title' => 'Invoice',
    ],
    'link' => [
        'no_contacts' => 'The invoice recipient has no linked Lexoffice contact.',
    ],
];
