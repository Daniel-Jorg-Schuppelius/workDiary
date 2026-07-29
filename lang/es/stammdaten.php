<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : stammdaten.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'identifier' => [
        'heading' => 'Datos maestros por revisar',
        'hint' => 'Estos datos no superan la validación. Corríjalos aquí: el cambio se transmite a los servicios conectados.',
        'suggestion' => 'Sugerencia: :value',
        'context' => [
            'bank_account' => 'Cuenta bancaria «:label»',
            'variant' => 'Variante :label',
            'bank_account_fallback' => 'Cuenta bancaria',
        ],
        'field' => [
            'vat_id' => 'NIF-IVA',
            'tax_number' => 'Número fiscal',
            'tax_identification_number' => 'Número de identificación fiscal',
            'bank_iban' => 'IBAN',
            'bank_bic' => 'BIC',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'gtin' => 'GTIN',
        ],
        'reason' => [
            'tax_number_in_vat_field' => 'Parece un número fiscal, pero está en el campo del NIF-IVA.',
            'vat_invalid' => 'NIF-IVA no válido (dígito de control incorrecto).',
            'tax_number_too_short' => 'Demasiado corto para un número fiscal alemán (10 a 13 dígitos).',
            'tax_number_invalid' => 'Número fiscal alemán no válido.',
            'tax_id_invalid' => 'Número de identificación fiscal no válido (dígito de control incorrecto).',
            'iban_invalid' => 'IBAN no válido (longitud o dígito de control).',
            'bic_invalid' => 'BIC no válido (8 u 11 caracteres).',
            'gtin_invalid' => 'GTIN no válida (dígito de control incorrecto).',
            'generic' => 'El valor no supera la validación.',
        ],
    ],
];
