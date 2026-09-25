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
        'title' => 'Auffällige Muster (Serienfehler, Chargen)',
        'hint' => 'Gruppen mit mindestens :threshold Reklamationen im Zeitraum. Hinweis, keine Entscheidung.',
        'none' => 'Keine auffälligen Muster im Zeitraum.',
        'rule_label' => 'Regel',
        'group' => 'Gruppe',
        'cases' => 'Fälle',
        'count' => 'Anzahl',
        'rule' => [
            'lot' => 'Charge',
            'article_defect' => 'Artikel × Mangelart',
            'article_cause' => 'Artikel × Ursache',
            'supplier_defect' => 'Lieferant × Mangelart',
            'entry_type_cause' => 'Auftragsart × Ursache',
        ],
        'notify_title' => 'Auffälliges Reklamationsmuster: :label',
        'notify_message' => ':count Reklamationen in :days Tagen (:rule).',
    ],
];
