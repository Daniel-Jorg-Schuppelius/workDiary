<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Leasing/Finanzierung: IFRS-16-/HGB-Einschätzung (MVP-947).
return [
    'classification' => [
        'title' => 'Einschätzung IFRS 16 / HGB',
        'disclaimer' => 'Vorbereitende Referenz ohne Bilanzierungszusage; die Beurteilung trifft die Buchhaltung bzw. Steuerberatung.',
        'ifrs16' => 'IFRS 16 (Leasingnehmer)',
        'hgb' => 'HGB/Steuer (Zurechnung)',
        'ratio' => 'Grundmietzeit / Nutzungsdauer',
        'months' => 'Monate',
        'reasons' => 'Begründung',
        'assessed_at' => 'Eingeschätzt am :date',
        'assess' => 'Einschätzen',
        'ifrs16_result' => [
            'short_term' => 'kurzfristig — Ausnahme möglich',
            'low_value' => 'geringwertig — Ausnahme möglich',
            'right_of_use' => 'Nutzungsrecht und Leasingverbindlichkeit ansetzen',
        ],
        'hgb_result' => [
            'lessor' => 'Zurechnung beim Leasinggeber',
            'lessee' => 'Zurechnung beim Leasingnehmer',
            'unknown' => 'nicht einschätzbar (Angaben fehlen)',
        ],
        'reason' => [
            'special_lease' => 'Spezialleasing',
            'missing_inputs' => 'Laufzeit oder Nutzungsdauer fehlt',
            'term_below_40' => 'Grundmietzeit unter 40 % der Nutzungsdauer',
            'term_above_90' => 'Grundmietzeit über 90 % der Nutzungsdauer',
            'bargain_option' => 'Kaufoption unter dem Restwert',
            'term_within' => 'Grundmietzeit zwischen 40 % und 90 %, keine günstige Kaufoption',
        ],
        'field' => [
            'useful_life_months' => 'Nutzungsdauer (Monate)',
            'asset_value_amount' => 'Anschaffungswert neu',
            'is_special_lease' => 'Spezialleasing',
        ],
        'flash' => [
            'saved' => 'Einschätzung gespeichert.',
        ],
    ],
];
