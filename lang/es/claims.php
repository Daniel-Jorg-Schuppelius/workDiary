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
        'title' => 'Patrones llamativos (defectos en serie, lotes)',
        'hint' => 'Grupos con al menos :threshold reclamaciones en el periodo. Una indicación, no una decisión.',
        'none' => 'No hay patrones llamativos en el periodo.',
        'rule_label' => 'Regla',
        'group' => 'Grupo',
        'cases' => 'Casos',
        'count' => 'Cantidad',
        'rule' => [
            'lot' => 'Lote',
            'article_defect' => 'Artículo × tipo de defecto',
            'article_cause' => 'Artículo × causa',
            'supplier_defect' => 'Proveedor × tipo de defecto',
            'entry_type_cause' => 'Tipo de orden × causa',
        ],
        'notify_title' => 'Patrón de reclamaciones llamativo: :label',
        'notify_message' => ':count reclamaciones en :days días (:rule).',
    ],
];
