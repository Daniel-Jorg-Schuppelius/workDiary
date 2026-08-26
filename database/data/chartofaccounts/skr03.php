<?php
/*
 * Created on   : Sat Aug 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : skr03.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Kontenplan-Vorlage in Anlehnung an den Standardkontenrahmen SKR03
 * (Feature 125, MVP-672/678).
 *
 * EIGENE ZUSAMMENSTELLUNG, kein Abzug aus Herstellerunterlagen: Die Auswahl
 * umfasst rund 45 Konten für den Einstieg einer kleinen Organisation, nicht
 * den vollständigen Rahmen mit rund 1.500 Konten. Kontonummern sind fachliche
 * Fakten; die hier verwendeten wurden gegen öffentlich publizierte Quellen
 * geprüft (u. a. den Prüfungskontenplan des Klausurenverbunds der
 * Steuerberaterkammern). Rechtlicher Hintergrund:
 * ../WorkDiary-Architecture/kontenrahmen-lizenzrecherche-2026-08.md
 *
 * Gilt für Deutschland. Österreich (KFS/BW 6) und die Schweiz (Kontenrahmen
 * KMU) haben ausdrückliche Rechtevorbehalte — dafür gibt es bewusst KEINE
 * Vorlage.
 *
 * Die Vorlage ist ein Startpunkt, keine Steuerberatung: Kontenwahl und
 * Steuerzuordnung gehören fachlich geprüft, bevor damit gebucht wird.
 */

return [
    'code' => 'skr03',
    'name' => 'SKR03 (Auszug, Prozessgliederung)',
    'country' => 'DE',
    'description' => 'Einstiegs-Kontenplan in Anlehnung an den SKR03 — Gliederung nach Geschäftsprozessen, verbreitet bei Einzelunternehmen und Personengesellschaften.',

    'accounts' => [
        // Anlagevermögen und Kapital
        ['number' => '0027', 'name' => 'EDV-Software', 'type' => 'asset'],
        ['number' => '0320', 'name' => 'Fuhrpark', 'type' => 'asset'],
        ['number' => '0410', 'name' => 'Betriebs- und Geschäftsausstattung', 'type' => 'asset'],
        ['number' => '0480', 'name' => 'Geringwertige Wirtschaftsgüter', 'type' => 'asset'],
        ['number' => '0880', 'name' => 'Eigenkapital', 'type' => 'equity'],

        // Finanz- und Privatkonten
        ['number' => '1000', 'name' => 'Kasse', 'type' => 'asset', 'is_cash' => true],
        ['number' => '1200', 'name' => 'Bank', 'type' => 'asset', 'is_bank' => true],
        ['number' => '1360', 'name' => 'Geldtransit', 'type' => 'asset', 'is_clearing' => true],
        ['number' => '1400', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'asset', 'is_open_item' => true],
        ['number' => '1571', 'name' => 'Abziehbare Vorsteuer 7 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1576', 'name' => 'Abziehbare Vorsteuer 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1600', 'name' => 'Verbindlichkeiten aus Lieferungen und Leistungen', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '1740', 'name' => 'Verbindlichkeiten aus Lohn und Gehalt', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '1771', 'name' => 'Umsatzsteuer 7 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1780', 'name' => 'Umsatzsteuer-Vorauszahlungen', 'type' => 'liability', 'euer_category' => 'paid_vat'],
        ['number' => '1781', 'name' => 'Umsatzsteuer-Vorauszahlungen 1/11', 'type' => 'asset', 'euer_category' => 'paid_vat'],
        ['number' => '1800', 'name' => 'Privatentnahmen allgemein', 'type' => 'equity'],
        ['number' => '1890', 'name' => 'Privateinlagen', 'type' => 'equity'],

        // Zinsen
        ['number' => '2100', 'name' => 'Zinsen und ähnliche Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '2650', 'name' => 'Zinsen und ähnliche Erträge', 'type' => 'income', 'euer_category' => 'income'],

        // Wareneingang
        ['number' => '3200', 'name' => 'Wareneingang', 'type' => 'expense', 'euer_category' => 'expense'],

        // Betriebliche Aufwendungen
        ['number' => '4100', 'name' => 'Löhne und Gehälter', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4130', 'name' => 'Gesetzliche soziale Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4210', 'name' => 'Miete (unbewegliche Wirtschaftsgüter)', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4240', 'name' => 'Gas, Strom, Wasser', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4360', 'name' => 'Versicherungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4380', 'name' => 'Beiträge, Gebühren und sonstige Abgaben', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4500', 'name' => 'Fahrzeugkosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4510', 'name' => 'Kfz-Steuer', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4520', 'name' => 'Kfz-Versicherung', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4610', 'name' => 'Werbekosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4650', 'name' => 'Bewirtungskosten', 'type' => 'expense', 'euer_category' => 'limited_deductible', 'deductible_percent' => '70.00'],
        ['number' => '4660', 'name' => 'Reisekosten Arbeitnehmer', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4670', 'name' => 'Reisekosten Unternehmer', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4830', 'name' => 'Abschreibungen auf Sachanlagen', 'type' => 'expense', 'euer_category' => 'depreciation'],
        ['number' => '4855', 'name' => 'Sofortabschreibungen geringwertiger Wirtschaftsgüter', 'type' => 'expense', 'euer_category' => 'low_value_asset'],
        ['number' => '4900', 'name' => 'Sonstige betriebliche Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4920', 'name' => 'Telefon', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4930', 'name' => 'Bürobedarf', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4950', 'name' => 'Rechts- und Beratungskosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4957', 'name' => 'Abschluss- und Prüfungskosten', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '4970', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => 'expense', 'euer_category' => 'expense'],

        // Erlöse
        ['number' => '8100', 'name' => 'Steuerfreie Umsätze § 4 Nr. 8 ff. UStG', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '8125', 'name' => 'Steuerfreie innergemeinschaftliche Lieferungen § 4 Nr. 1b UStG', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '8300', 'name' => 'Erlöse 7 % USt', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '8400', 'name' => 'Erlöse 19 % USt', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '8731', 'name' => 'Gewährte Skonti 7 % USt', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '8736', 'name' => 'Gewährte Skonti 19 % USt', 'type' => 'expense', 'euer_category' => 'expense'],

        // Vortrag
        ['number' => '9000', 'name' => 'Saldenvorträge, Sachkonten', 'type' => 'equity'],
    ],

    'tax_codes' => [
        ['code' => 'USt19', 'name' => 'Umsatzsteuer 19 %', 'direction' => 'output', 'rate' => '19.00', 'tax_account' => '1776', 'ustva_base_field' => '81'],
        ['code' => 'USt7', 'name' => 'Umsatzsteuer 7 %', 'direction' => 'output', 'rate' => '7.00', 'tax_account' => '1771', 'ustva_base_field' => '86'],
        ['code' => 'VSt19', 'name' => 'Vorsteuer 19 %', 'direction' => 'input', 'rate' => '19.00', 'tax_account' => '1576', 'ustva_tax_field' => '66'],
        ['code' => 'VSt7', 'name' => 'Vorsteuer 7 %', 'direction' => 'input', 'rate' => '7.00', 'tax_account' => '1571', 'ustva_tax_field' => '66'],
        ['code' => 'IGL', 'name' => 'Innergemeinschaftliche Lieferung § 4 Nr. 1b UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '41'],
        ['code' => 'FREI', 'name' => 'Steuerfrei', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null],
    ],

    // Buchungsregeln: erst sie machen den Kontenplan benutzbar.
    'rules' => [
        ['source_kind' => 'sales_invoice', 'role' => 'receivable', 'account' => '1400'],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8400', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8300', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8100', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '1776', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '1771', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        ['source_kind' => 'incoming_invoice', 'role' => 'payable', 'account' => '1600'],
        ['source_kind' => 'incoming_invoice', 'role' => 'expense', 'account' => '4900'],
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1576', 'tax_code' => 'VSt19'],

        ['source_kind' => 'expense', 'role' => 'expense', 'account' => '4900'],
        ['source_kind' => 'expense', 'role' => 'tax_input', 'account' => '1576', 'tax_code' => 'VSt19'],
        ['source_kind' => 'expense', 'role' => 'employee_payable', 'account' => '1740'],

        ['source_kind' => 'cash_entry', 'role' => 'cash', 'account' => '1000'],
        ['source_kind' => 'cash_entry', 'role' => 'revenue', 'account' => '8400'],
        ['source_kind' => 'cash_entry', 'role' => 'expense', 'account' => '4900'],

        ['source_kind' => 'payment', 'role' => 'bank', 'account' => '1200'],
        ['source_kind' => 'payment', 'role' => 'receivable', 'account' => '1400'],
        ['source_kind' => 'payment', 'role' => 'employee_payable', 'account' => '1740'],
        ['source_kind' => 'payment', 'role' => 'discount', 'account' => '8736'],

        // Jahres-AfA (Feature 133): direkte Methode, BGA als Auffangkonto —
        // Fuhrpark/Software werden je Anlage überschrieben.
        ['source_kind' => 'depreciation', 'role' => 'fixed_asset', 'account' => '0410'],
        ['source_kind' => 'depreciation', 'role' => 'depreciation', 'account' => '4830'],
    ],
];
