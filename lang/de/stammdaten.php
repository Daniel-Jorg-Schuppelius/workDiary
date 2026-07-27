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
        'heading' => 'Prüfbedürftige Stammdaten',
        'hint' => 'Diese Angaben halten der Prüfung nicht stand. Korrigieren Sie sie hier — die Änderung wird an die angebundenen Dienste übertragen.',
        'suggestion' => 'Vorschlag: :value',
        'field' => [
            'vat_id' => 'USt-IdNr.',
            'tax_number' => 'Steuernummer',
            'tax_identification_number' => 'Steuerliche Identifikationsnummer',
            'bank_iban' => 'IBAN',
            'bank_bic' => 'BIC',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'gtin' => 'GTIN',
        ],
        'reason' => [
            'tax_number_in_vat_field' => 'Sieht nach einer Steuernummer aus, steht aber im Feld für die USt-IdNr.',
            'vat_invalid' => 'Keine gültige USt-IdNr. (Prüfziffer stimmt nicht).',
            'tax_number_too_short' => 'Zu kurz für eine deutsche Steuernummer (10 bis 13 Stellen).',
            'tax_number_invalid' => 'Keine gültige deutsche Steuernummer.',
            'tax_id_invalid' => 'Keine gültige steuerliche Identifikationsnummer (Prüfziffer stimmt nicht).',
            'iban_invalid' => 'Keine gültige IBAN (Länge oder Prüfziffer stimmt nicht).',
            'bic_invalid' => 'Kein gültiger BIC (zulässig sind 8 oder 11 Stellen).',
            'gtin_invalid' => 'Keine gültige GTIN (Prüfziffer stimmt nicht).',
            'generic' => 'Der Wert hält der Prüfung nicht stand.',
        ],
    ],
];
