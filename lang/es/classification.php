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
        'heading' => 'Calidad de datos: faltan clasificaciones obligatorias',
        'missing' => ':domain ausente',
    ],
    'error' => [
        'requirementUnmet' => 'Faltan clasificaciones obligatorias: :domains',
        'requirementMin' => 'Clasificación obligatoria «:domain»: se requieren al menos :min entrada(s), presentes: :actual.',
        'requirementMax' => 'Clasificación obligatoria «:domain»: se permiten como máximo :max entrada(s), presentes: :actual.',
        'referenced' => 'El valor «:label» todavía se utiliza (:usage) y no se puede eliminar. Desactívelo en su lugar: desaparece de las nuevas selecciones y los registros existentes lo conservan.',
    ],
    'action' => [
        'deactivate' => 'Desactivar',
        'deactivate_confirm' => '¿Desactivar este valor? Ya no se podrá seleccionar; los registros existentes lo conservan.',
    ],
    'flash' => [
        'deactivated' => '«:label» se ha desactivado.',
    ],
    'reference' => [
        'time_entries' => 'Registros de tiempo',
        'claim_cases' => 'Reclamaciones',
        'products' => 'Artículos',
        'classifiables' => 'Asignaciones en registros',
        'import_value_mappings' => 'Asignaciones de importación',
        'assets' => 'Activos',
    ],
];
