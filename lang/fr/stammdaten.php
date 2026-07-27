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
        'heading' => 'Données de base à vérifier',
        'hint' => 'Ces informations échouent à la validation. Corrigez-les ici — la modification est transmise aux services connectés.',
        'suggestion' => 'Proposition : :value',
        'field' => [
            'vat_id' => 'N° de TVA',
            'tax_number' => 'Numéro fiscal',
            'tax_identification_number' => 'Numéro d’identification fiscale',
            'bank_iban' => 'IBAN',
            'bank_bic' => 'BIC',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'gtin' => 'GTIN',
        ],
        'reason' => [
            'tax_number_in_vat_field' => 'Ressemble à un numéro fiscal mais figure dans le champ du n° de TVA.',
            'vat_invalid' => 'N° de TVA non valide (clé de contrôle incorrecte).',
            'tax_number_too_short' => 'Trop court pour un numéro fiscal allemand (10 à 13 chiffres).',
            'tax_number_invalid' => 'Numéro fiscal allemand non valide.',
            'tax_id_invalid' => 'Numéro d’identification fiscale non valide (clé de contrôle incorrecte).',
            'iban_invalid' => 'IBAN non valide (longueur ou clé de contrôle).',
            'bic_invalid' => 'BIC non valide (8 ou 11 caractères).',
            'gtin_invalid' => 'GTIN non valide (clé de contrôle incorrecte).',
            'generic' => 'La valeur échoue à la validation.',
        ],
    ],
];
