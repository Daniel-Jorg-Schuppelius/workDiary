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
        'heading' => 'Anagrafiche da verificare',
        'hint' => 'Questi dati non superano la verifica. Correggili qui — la modifica viene trasmessa ai servizi collegati.',
        'suggestion' => 'Proposta: :value',
        'context' => [
            'bank_account' => 'Coordinate bancarie «:label»',
            'variant' => 'Variante :label',
            'bank_account_fallback' => 'Coordinate bancarie',
        ],
        'field' => [
            'vat_id' => 'Partita IVA',
            'tax_number' => 'Codice fiscale (numero fiscale)',
            'tax_identification_number' => 'Numero di identificazione fiscale',
            'bank_iban' => 'IBAN',
            'bank_bic' => 'BIC',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'gtin' => 'GTIN',
        ],
        'reason' => [
            'tax_number_in_vat_field' => 'Sembra un numero fiscale ma si trova nel campo della partita IVA.',
            'vat_invalid' => 'Partita IVA non valida (cifra di controllo errata).',
            'tax_number_too_short' => 'Troppo corto per un numero fiscale tedesco (da 10 a 13 cifre).',
            'tax_number_invalid' => 'Numero fiscale tedesco non valido.',
            'tax_id_invalid' => 'Numero di identificazione fiscale non valido (cifra di controllo errata).',
            'iban_invalid' => 'IBAN non valido (lunghezza o cifra di controllo).',
            'bic_invalid' => 'BIC non valido (8 o 11 caratteri).',
            'gtin_invalid' => 'GTIN non valido (cifra di controllo errata).',
            'generic' => 'Il valore non supera la verifica.',
        ],
    ],
];
