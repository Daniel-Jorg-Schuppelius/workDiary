<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : skr04.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Kontenplan-Vorlage in Anlehnung an den Standardkontenrahmen SKR04
 * (Feature 125, MVP-672/678) — Gliederung nach dem Abschlussgliederungs-
 * prinzip, verbreitet bei Kapitalgesellschaften.
 *
 * Dieselben Regeln wie bei der SKR03-Vorlage: eigene Zusammenstellung,
 * Auszug für den Einstieg, Deutschland only, fachliche Prüfung vorausgesetzt.
 * Hintergrund: ../WorkDiary-Architecture/kontenrahmen-lizenzrecherche-2026-08.md
 */

return [
    'code' => 'skr04',
    'name' => 'SKR04 (Auszug, Abschlussgliederung)',
    'country' => 'DE',
    'description' => 'Einstiegs-Kontenplan in Anlehnung an den SKR04 — Gliederung nach Bilanz und Gewinn- und Verlustrechnung, verbreitet bei Kapitalgesellschaften.',

    'accounts' => [
        // Anlagevermögen
        ['number' => '0135', 'name' => 'EDV-Software', 'type' => 'asset'],
        ['number' => '0500', 'name' => 'Andere Anlagen, Betriebs- und Geschäftsausstattung', 'type' => 'asset'],
        ['number' => '0520', 'name' => 'Pkw', 'type' => 'asset'],
        ['number' => '0670', 'name' => 'Geringwertige Wirtschaftsgüter', 'type' => 'asset'],

        // Umlaufvermögen
        ['number' => '1200', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'asset', 'is_open_item' => true],
        ['number' => '1300', 'name' => 'Sonstige Vermögensgegenstände', 'type' => 'asset'],
        ['number' => '1401', 'name' => 'Abziehbare Vorsteuer 7 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1406', 'name' => 'Abziehbare Vorsteuer 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1460', 'name' => 'Geldtransit', 'type' => 'asset', 'is_clearing' => true],
        ['number' => '1600', 'name' => 'Kasse', 'type' => 'asset', 'is_cash' => true],
        ['number' => '1800', 'name' => 'Bank', 'type' => 'asset', 'is_bank' => true],

        // Eigenkapital und Privat
        ['number' => '2000', 'name' => 'Festkapital', 'type' => 'equity'],
        ['number' => '2100', 'name' => 'Privatentnahmen allgemein', 'type' => 'equity'],
        ['number' => '2180', 'name' => 'Privateinlagen', 'type' => 'equity'],

        // Verbindlichkeiten
        ['number' => '3150', 'name' => 'Verbindlichkeiten gegenüber Kreditinstituten', 'type' => 'liability'],
        ['number' => '3300', 'name' => 'Verbindlichkeiten aus Lieferungen und Leistungen', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '3720', 'name' => 'Verbindlichkeiten aus Lohn und Gehalt', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '3801', 'name' => 'Umsatzsteuer 7 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3806', 'name' => 'Umsatzsteuer 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3820', 'name' => 'Umsatzsteuer-Vorauszahlungen', 'type' => 'liability', 'euer_category' => 'paid_vat'],
        ['number' => '3830', 'name' => 'Umsatzsteuer-Vorauszahlungen 1/11', 'type' => 'asset', 'euer_category' => 'paid_vat'],

        // Erlöse
        ['number' => '4100', 'name' => 'Steuerfreie Umsätze § 4 Nr. 8 ff. UStG', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '4125', 'name' => 'Steuerfreie innergemeinschaftliche Lieferungen § 4 Nr. 1b UStG', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '4300', 'name' => 'Erlöse 7 % USt', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '4400', 'name' => 'Erlöse 19 % USt', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '4731', 'name' => 'Gewährte Skonti 7 % USt', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4736', 'name' => 'Gewährte Skonti 19 % USt', 'type' => 'expense', 'euer_category' => 'expense'],

        // Material
        ['number' => '5400', 'name' => 'Wareneingang', 'type' => 'expense', 'euer_category' => 'expense'],

        // Betriebliche Aufwendungen
        ['number' => '6000', 'name' => 'Löhne und Gehälter', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6110', 'name' => 'Gesetzliche soziale Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6200', 'name' => 'Abschreibungen auf immaterielle Vermögensgegenstände', 'type' => 'expense', 'euer_category' => 'depreciation'],
        ['number' => '6220', 'name' => 'Abschreibungen auf Sachanlagen', 'type' => 'expense', 'euer_category' => 'depreciation'],
        ['number' => '6260', 'name' => 'Sofortabschreibungen geringwertiger Wirtschaftsgüter', 'type' => 'expense', 'euer_category' => 'low_value_asset'],
        ['number' => '6300', 'name' => 'Sonstige betriebliche Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6310', 'name' => 'Miete (unbewegliche Wirtschaftsgüter)', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6325', 'name' => 'Gas, Strom, Wasser', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6400', 'name' => 'Versicherungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6500', 'name' => 'Fahrzeugkosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6520', 'name' => 'Kfz-Versicherungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6600', 'name' => 'Werbekosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6640', 'name' => 'Bewirtungskosten', 'type' => 'expense', 'euer_category' => 'limited_deductible', 'deductible_percent' => '70.00'],
        ['number' => '6670', 'name' => 'Reisekosten Unternehmer', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6805', 'name' => 'Telefon', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6810', 'name' => 'Telefax und Internetkosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6815', 'name' => 'Bürobedarf', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6825', 'name' => 'Rechts- und Beratungskosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6827', 'name' => 'Abschluss- und Prüfungskosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6830', 'name' => 'Buchführungskosten', 'type' => 'expense', 'euer_category' => 'expense'],

        // Vortrag
        ['number' => '9000', 'name' => 'Saldenvorträge, Sachkonten', 'type' => 'equity'],
    ],

    'tax_codes' => [
        ['code' => 'USt19', 'name' => 'Umsatzsteuer 19 %', 'direction' => 'output', 'rate' => '19.00', 'tax_account' => '3806', 'ustva_base_field' => '81'],
        ['code' => 'USt7', 'name' => 'Umsatzsteuer 7 %', 'direction' => 'output', 'rate' => '7.00', 'tax_account' => '3801', 'ustva_base_field' => '86'],
        ['code' => 'VSt19', 'name' => 'Vorsteuer 19 %', 'direction' => 'input', 'rate' => '19.00', 'tax_account' => '1406', 'ustva_tax_field' => '66'],
        ['code' => 'VSt7', 'name' => 'Vorsteuer 7 %', 'direction' => 'input', 'rate' => '7.00', 'tax_account' => '1401', 'ustva_tax_field' => '66'],
        ['code' => 'IGL', 'name' => 'Innergemeinschaftliche Lieferung § 4 Nr. 1b UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '41'],
        ['code' => 'FREI', 'name' => 'Steuerfrei', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null],
    ],

    'rules' => [
        ['source_kind' => 'sales_invoice', 'role' => 'receivable', 'account' => '1200'],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4400', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4300', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4100', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '3806', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '3801', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        ['source_kind' => 'incoming_invoice', 'role' => 'payable', 'account' => '3300'],
        ['source_kind' => 'incoming_invoice', 'role' => 'expense', 'account' => '6300'],
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1406', 'tax_code' => 'VSt19'],

        ['source_kind' => 'expense', 'role' => 'expense', 'account' => '6300'],
        ['source_kind' => 'expense', 'role' => 'tax_input', 'account' => '1406', 'tax_code' => 'VSt19'],
        ['source_kind' => 'expense', 'role' => 'employee_payable', 'account' => '3720'],

        ['source_kind' => 'cash_entry', 'role' => 'cash', 'account' => '1600'],
        ['source_kind' => 'cash_entry', 'role' => 'revenue', 'account' => '4400'],
        ['source_kind' => 'cash_entry', 'role' => 'expense', 'account' => '6300'],

        ['source_kind' => 'payment', 'role' => 'bank', 'account' => '1800'],
        ['source_kind' => 'payment', 'role' => 'receivable', 'account' => '1200'],
        ['source_kind' => 'payment', 'role' => 'employee_payable', 'account' => '3720'],
        ['source_kind' => 'payment', 'role' => 'discount', 'account' => '4736'],
    ],
];
