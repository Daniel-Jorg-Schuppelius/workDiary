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
 * ../WorkDiary-Architecture/chronik/analysen/kontenrahmen-lizenzrecherche-2026-08.md
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
        // Steuerschuld des Empfängers (MVP-1621): Vorsteuer aus innergemeinschaftlichem Erwerb und nach § 13b UStG.
        ['number' => '1572', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1574', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1576', 'name' => 'Abziehbare Vorsteuer 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1577', 'name' => 'Abziehbare Vorsteuer nach § 13b UStG 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1600', 'name' => 'Verbindlichkeiten aus Lieferungen und Leistungen', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '1710', 'name' => 'Erhaltene Anzahlungen (Verbindlichkeiten)', 'type' => 'liability'],
        ['number' => '1711', 'name' => 'Erhaltene, versteuerte Anzahlungen 7 % USt (Verbindlichkeiten)', 'type' => 'liability', 'datev_automatic' => 'USt7'],
        ['number' => '1718', 'name' => 'Erhaltene, versteuerte Anzahlungen 19 % USt (Verbindlichkeiten)', 'type' => 'liability', 'datev_automatic' => 'USt19'],
        ['number' => '1740', 'name' => 'Verbindlichkeiten aus Lohn und Gehalt', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '1771', 'name' => 'Umsatzsteuer 7 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1772', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1774', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1776', 'name' => 'Umsatzsteuer 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1787', 'name' => 'Umsatzsteuer nach § 13b UStG 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '1780', 'name' => 'Umsatzsteuer-Vorauszahlungen', 'type' => 'liability', 'euer_category' => 'paid_vat'],
        ['number' => '1781', 'name' => 'Umsatzsteuer-Vorauszahlungen 1/11', 'type' => 'asset', 'euer_category' => 'paid_vat'],
        ['number' => '1800', 'name' => 'Privatentnahmen allgemein', 'type' => 'equity'],
        ['number' => '1890', 'name' => 'Privateinlagen', 'type' => 'equity'],

        // Zinsen und sonstige Aufwendungen/Erträge
        ['number' => '2100', 'name' => 'Zinsen und ähnliche Aufwendungen', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '2310', 'name' => 'Anlagenabgänge Sachanlagen (Restbuchwert bei Buchverlust)', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '2315', 'name' => 'Anlagenabgänge Sachanlagen (Restbuchwert bei Buchgewinn)', 'type' => 'income', 'euer_category' => 'income'],
        // Vorgabe des Ausbuchungskontos beim OPOS-Ausgleich (MVP-1555).
        ['number' => '2400', 'name' => 'Forderungsverluste (übliche Höhe)', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '2650', 'name' => 'Zinsen und ähnliche Erträge', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '2709', 'name' => 'Sonstige Erträge unregelmäßig', 'type' => 'income', 'euer_category' => 'income'],

        // Wareneingang
        ['number' => '3200', 'name' => 'Wareneingang', 'type' => 'expense', 'euer_category' => 'expense'],

        // Erhaltenes Skonto mindert den Wareneinsatz (Haben auf einem Aufwandskonto).
        ['number' => '3731', 'name' => 'Erhaltene Skonti 7 % Vorsteuer', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'VSt7'],
        ['number' => '3736', 'name' => 'Erhaltene Skonti 19 % Vorsteuer', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'VSt19'],

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

        // Erlöse; `datev_automatic` = DATEV-Automatikkonto, der Export setzt dort keinen Steuerschlüssel (MVP-1622).
        ['number' => '8100', 'name' => 'Steuerfreie Umsätze § 4 Nr. 8 ff. UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'FREI'],
        ['number' => '8120', 'name' => 'Steuerfreie Umsätze § 4 Nr. 1a UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'AUSF'],
        ['number' => '8125', 'name' => 'Steuerfreie innergemeinschaftliche Lieferungen § 4 Nr. 1b UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'IGL'],
        ['number' => '8300', 'name' => 'Erlöse 7 % USt', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'USt7'],
        // Kennziffer 21 und 45 (MVP-1623): sonstige Leistung ins übrige Gemeinschaftsgebiet, nicht steuerbarer Umsatz im Drittland.
        ['number' => '8336', 'name' => 'Erlöse aus im anderen EU-Land steuerpflichtigen sonstigen Leistungen, für die der Leistungsempfänger die Umsatzsteuer schuldet', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'RC21'],
        ['number' => '8337', 'name' => 'Erlöse aus Leistungen, für die der Leistungsempfänger die Steuer nach § 13b UStG schuldet', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'RC60'],
        ['number' => '8338', 'name' => 'Erlöse aus im Drittland steuerbaren Leistungen, im Inland nicht steuerbare Umsätze', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'NSTB'],
        ['number' => '8400', 'name' => 'Erlöse 19 % USt', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'USt19'],
        ['number' => '8731', 'name' => 'Gewährte Skonti 7 % USt', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'USt7'],
        ['number' => '8736', 'name' => 'Gewährte Skonti 19 % USt', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'USt19'],

        // Vortrag
        ['number' => '9000', 'name' => 'Saldenvorträge, Sachkonten', 'type' => 'equity'],
    ],

    // `datev_tax_key`: BU-Schlüssel des DATEV-Buchungsstapels (Steuerschlüssel-Legende des DATEV-SKR).
    'tax_codes' => [
        ['code' => 'USt19', 'name' => 'Umsatzsteuer 19 %', 'direction' => 'output', 'rate' => '19.00', 'tax_account' => '1776', 'ustva_base_field' => '81', 'datev_tax_key' => '3'],
        ['code' => 'USt7', 'name' => 'Umsatzsteuer 7 %', 'direction' => 'output', 'rate' => '7.00', 'tax_account' => '1771', 'ustva_base_field' => '86', 'datev_tax_key' => '2'],
        ['code' => 'VSt19', 'name' => 'Vorsteuer 19 %', 'direction' => 'input', 'rate' => '19.00', 'tax_account' => '1576', 'ustva_tax_field' => '66', 'datev_tax_key' => '9'],
        ['code' => 'VSt7', 'name' => 'Vorsteuer 7 %', 'direction' => 'input', 'rate' => '7.00', 'tax_account' => '1571', 'ustva_tax_field' => '66', 'datev_tax_key' => '8'],
        ['code' => 'IGL', 'name' => 'Innergemeinschaftliche Lieferung § 4 Nr. 1b UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '41', 'tax_category' => 'K'],
        // Umsätze ohne Steuer: Die Buchungs-Inbox wählt das Kennzeichen über die Steuerkategorie des Belegs.
        ['code' => 'RC21', 'name' => 'Nicht steuerbare sonstige Leistung § 18b UStG (Steuerschuld des Empfängers)', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '21', 'tax_category' => 'AE', 'datev_tax_key' => '47'],
        ['code' => 'RC60', 'name' => 'Steuerpflichtiger Umsatz, Steuerschuld des Leistungsempfängers § 13b Abs. 5 UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '60', 'tax_category' => 'AE', 'datev_tax_key' => '46'],
        ['code' => 'AUSF', 'name' => 'Steuerfreie Ausfuhrlieferung § 4 Nr. 1a UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '43', 'tax_category' => 'G', 'datev_tax_key' => '1'],
        ['code' => 'NSTB', 'name' => 'Nicht steuerbarer Umsatz (Leistungsort nicht im Inland)', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '45', 'tax_category' => 'O'],
        ['code' => 'FREI', 'name' => 'Steuerfrei', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'tax_category' => 'E'],
        // Steuerschuld des Empfängers (MVP-1621): Umsatzsteuer auf `tax_account`, Vorsteuer auf `input_tax_account`; der Rechnungseingang wählt über `tax_case`.
        ['code' => 'RC13BEU', 'name' => '§ 13b Abs. 1 UStG 19 %: sonstige Leistung aus dem übrigen Gemeinschaftsgebiet', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '1787', 'input_tax_account' => '1577', 'ustva_base_field' => '46', 'ustva_tax_field' => '47', 'ustva_input_tax_field' => '67', 'tax_case' => 'reverse_charge_eu', 'datev_tax_key' => '94'],
        ['code' => 'RC13B', 'name' => '§ 13b Abs. 2 UStG 19 %: Steuerschuld des Leistungsempfängers', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '1787', 'input_tax_account' => '1577', 'ustva_base_field' => '84', 'ustva_tax_field' => '85', 'ustva_input_tax_field' => '67', 'tax_case' => 'reverse_charge', 'datev_tax_key' => '94'],
        ['code' => 'IGE19', 'name' => 'Innergemeinschaftlicher Erwerb 19 %', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '1774', 'input_tax_account' => '1574', 'ustva_base_field' => '89', 'ustva_tax_field' => '89', 'ustva_input_tax_field' => '61', 'tax_case' => 'intra_community_acquisition', 'datev_tax_key' => '19'],
        ['code' => 'IGE7', 'name' => 'Innergemeinschaftlicher Erwerb 7 %', 'direction' => 'both', 'rate' => '7.00', 'tax_account' => '1772', 'input_tax_account' => '1572', 'ustva_base_field' => '93', 'ustva_tax_field' => '93', 'ustva_input_tax_field' => '61', 'tax_case' => 'intra_community_acquisition', 'datev_tax_key' => '18'],
    ],

    // Buchungsregeln: erst sie machen den Kontenplan benutzbar.
    'rules' => [
        ['source_kind' => 'sales_invoice', 'role' => 'receivable', 'account' => '1400'],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8400', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8300', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8100', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        // Umsätze ohne Steuer auf das Konto ihres Kennzeichens (MVP-1622): DATEV ordnet die Kennziffer über das Konto zu.
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8125', 'match' => ['tax_code' => 'IGL']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8337', 'match' => ['tax_code' => 'RC60']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8336', 'match' => ['tax_code' => 'RC21']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8120', 'match' => ['tax_code' => 'AUSF']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '8338', 'match' => ['tax_code' => 'NSTB']],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '1776', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '1771', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        ['source_kind' => 'incoming_invoice', 'role' => 'payable', 'account' => '1600'],
        ['source_kind' => 'incoming_invoice', 'role' => 'expense', 'account' => '4900'],
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1576', 'tax_code' => 'VSt19'],
        // Mehrere Sätze je Eingangsrechnung (MVP-1622): Vorsteuer je Satz.
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1571', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'VSt7'],

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
        ['source_kind' => 'payment', 'role' => 'discount', 'account' => '8731', 'match' => ['tax_rate' => '7.00']],

        // Online-Zahlung (MVP-1067): Geldtransit bis zur Auszahlung, Gebühr als Nebenkosten des Geldverkehrs.
        ['source_kind' => 'online_payment', 'role' => 'payment_transit', 'account' => '1360'],
        ['source_kind' => 'online_payment', 'role' => 'receivable', 'account' => '1400'],
        ['source_kind' => 'online_payment', 'role' => 'payment_fees', 'account' => '4970'],
        // Angerechnete Vorauszahlung ohne lokale Rechnung (MVP-1523): erhaltene Anzahlung netto, Umsatzsteuer bei Zufluss.
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '1718', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '1711', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '1710', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        ['source_kind' => 'online_payment', 'role' => 'tax_output', 'account' => '1776', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'online_payment', 'role' => 'tax_output', 'account' => '1771', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        // Jahres-AfA (Feature 133): direkte Methode, BGA als Auffangkonto —
        // Fuhrpark/Software werden je Anlage überschrieben.
        ['source_kind' => 'depreciation', 'role' => 'fixed_asset', 'account' => '0410'],
        ['source_kind' => 'depreciation', 'role' => 'depreciation', 'account' => '4830'],

        // Anlagenabgang (MVP-891): Restbuchwert gegen Buchverlust bzw. -gewinn.
        ['source_kind' => 'asset_disposal', 'role' => 'fixed_asset', 'account' => '0410'],
        ['source_kind' => 'asset_disposal', 'role' => 'disposal_loss', 'account' => '2310'],
        ['source_kind' => 'asset_disposal', 'role' => 'disposal_gain', 'account' => '2315'],
    ],
];
