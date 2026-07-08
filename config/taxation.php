<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : taxation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Steuersatz-Katalog je Land (Restpunkt 68) für die LOKALE Fakturierung
 * (nur ohne externes Faktura-Programm relevant — Rechnungshoheit-Grundsatz
 * bleibt unangetastet). Org-Override: settings.invoicing.default_tax_rate
 * gewinnt für Inlandsfälle. Reverse-Charge: EU-B2B mit formal gültiger
 * USt-IdNr. (Prüfung übers common-toolkit) → 0 % + Pflichthinweis.
 */
return [
    // Standard-/ermäßigte Sätze je Land (Prozent).
    'rates' => [
        'DE' => ['standard' => '19.00', 'reduced' => '7.00'],
        'AT' => ['standard' => '20.00', 'reduced' => '10.00'],
        'CH' => ['standard' => '8.10', 'reduced' => '2.60'],
        'FR' => ['standard' => '20.00', 'reduced' => '5.50'],
        'IT' => ['standard' => '22.00', 'reduced' => '10.00'],
        'ES' => ['standard' => '21.00', 'reduced' => '10.00'],
        'NL' => ['standard' => '21.00', 'reduced' => '9.00'],
        'PL' => ['standard' => '23.00', 'reduced' => '8.00'],
    ],

    // EU-Mitgliedstaaten (für die Reverse-Charge-Entscheidung).
    'eu_countries' => [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR',
        'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL',
        'PT', 'RO', 'SE', 'SI', 'SK',
    ],

    'notes' => [
        'reverse_charge' => 'Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge, §13b UStG / Art. 196 MwStSystRL).',
        'export' => 'Nicht im Inland steuerbare Leistung (Leistungsort beim Empfänger, §3a UStG).',
    ],
];
