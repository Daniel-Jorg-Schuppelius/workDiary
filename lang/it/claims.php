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
        'title' => 'Schemi rilevanti (difetti di serie, lotti)',
        'hint' => 'Gruppi con almeno :threshold reclami nel periodo. Un’indicazione, non una decisione.',
        'none' => 'Nessuno schema rilevante nel periodo.',
        'rule_label' => 'Regola',
        'group' => 'Gruppo',
        'cases' => 'Casi',
        'count' => 'Numero',
        'rule' => [
            'lot' => 'Lotto',
            'article_defect' => 'Articolo × tipo di difetto',
            'article_cause' => 'Articolo × causa',
            'supplier_defect' => 'Fornitore × tipo di difetto',
            'entry_type_cause' => 'Tipo di ordine × causa',
        ],
        'notify_title' => 'Schema di reclami rilevante: :label',
        'notify_message' => ':count reclami in :days giorni (:rule).',
    ],
];
