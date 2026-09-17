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
    ],
];
