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
 * Hintergrund: ../WorkDiary-Architecture/chronik/analysen/kontenrahmen-lizenzrecherche-2026-08.md
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
        // Steuerschuld des Empfängers (MVP-1621): Vorsteuer aus innergemeinschaftlichem Erwerb und nach § 13b UStG.
        ['number' => '1402', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1404', 'name' => 'Abziehbare Vorsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1406', 'name' => 'Abziehbare Vorsteuer 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
        ['number' => '1407', 'name' => 'Abziehbare Vorsteuer nach § 13b UStG 19 %', 'type' => 'asset', 'euer_category' => 'input_tax'],
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
        ['number' => '3250', 'name' => 'Erhaltene Anzahlungen auf Bestellungen', 'type' => 'liability'],
        ['number' => '3260', 'name' => 'Erhaltene, versteuerte Anzahlungen 7 % USt (Verbindlichkeiten)', 'type' => 'liability', 'datev_automatic' => 'USt7'],
        ['number' => '3272', 'name' => 'Erhaltene, versteuerte Anzahlungen 19 % USt (Verbindlichkeiten)', 'type' => 'liability', 'datev_automatic' => 'USt19'],
        ['number' => '3720', 'name' => 'Verbindlichkeiten aus Lohn und Gehalt', 'type' => 'liability', 'is_open_item' => true],
        ['number' => '3801', 'name' => 'Umsatzsteuer 7 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3802', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3804', 'name' => 'Umsatzsteuer aus innergemeinschaftlichem Erwerb 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3806', 'name' => 'Umsatzsteuer 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3837', 'name' => 'Umsatzsteuer nach § 13b UStG 19 %', 'type' => 'liability', 'euer_category' => 'income_vat'],
        ['number' => '3820', 'name' => 'Umsatzsteuer-Vorauszahlungen', 'type' => 'liability', 'euer_category' => 'paid_vat'],
        ['number' => '3830', 'name' => 'Umsatzsteuer-Vorauszahlungen 1/11', 'type' => 'asset', 'euer_category' => 'paid_vat'],

        // Erlöse; `datev_automatic` = DATEV-Automatikkonto, der Export setzt dort keinen Steuerschlüssel (MVP-1622).
        ['number' => '4100', 'name' => 'Steuerfreie Umsätze § 4 Nr. 8 ff. UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'FREI'],
        ['number' => '4120', 'name' => 'Steuerfreie Umsätze § 4 Nr. 1a UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'AUSF'],
        ['number' => '4125', 'name' => 'Steuerfreie innergemeinschaftliche Lieferungen § 4 Nr. 1b UStG', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'IGL'],
        ['number' => '4300', 'name' => 'Erlöse 7 % USt', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'USt7'],
        // Kennziffer 21 und 45 (MVP-1623): sonstige Leistung ins übrige Gemeinschaftsgebiet, nicht steuerbarer Umsatz im Drittland.
        ['number' => '4336', 'name' => 'Erlöse aus im anderen EU-Land steuerpflichtigen sonstigen Leistungen, für die der Leistungsempfänger die Umsatzsteuer schuldet', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'RC21'],
        ['number' => '4337', 'name' => 'Erlöse aus Leistungen, für die der Leistungsempfänger die Steuer nach § 13b UStG schuldet', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'RC60'],
        ['number' => '4338', 'name' => 'Erlöse aus im Drittland steuerbaren Leistungen, im Inland nicht steuerbare Umsätze', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'NSTB'],
        ['number' => '4400', 'name' => 'Erlöse 19 % USt', 'type' => 'income', 'euer_category' => 'income', 'datev_automatic' => 'USt19'],
        ['number' => '4731', 'name' => 'Gewährte Skonti 7 % USt', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'USt7'],
        ['number' => '4736', 'name' => 'Gewährte Skonti 19 % USt', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'USt19'],
        ['number' => '4839', 'name' => 'Sonstige Erträge unregelmäßig', 'type' => 'income', 'euer_category' => 'income'],

        // Material
        ['number' => '4855', 'name' => 'Anlagenabgänge Sachanlagen (Restbuchwert bei Buchgewinn)', 'type' => 'income', 'euer_category' => 'income'],
        ['number' => '5400', 'name' => 'Wareneingang', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'VSt19'],

        // Erhaltenes Skonto mindert den Wareneinsatz (Haben auf einem Aufwandskonto).
        ['number' => '5731', 'name' => 'Erhaltene Skonti 7 % Vorsteuer', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'VSt7'],
        ['number' => '5736', 'name' => 'Erhaltene Skonti 19 % Vorsteuer', 'type' => 'expense', 'euer_category' => 'expense', 'datev_automatic' => 'VSt19'],

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
        ['number' => '6855', 'name' => 'Nebenkosten des Geldverkehrs', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '6895', 'name' => 'Anlagenabgänge Sachanlagen (Restbuchwert bei Buchverlust)', 'type' => 'expense', 'euer_category' => 'expense'],
        // Vorgabe des Ausbuchungskontos beim OPOS-Ausgleich (MVP-1555).
        ['number' => '6930', 'name' => 'Forderungsverluste (übliche Höhe)', 'type' => 'expense', 'euer_category' => 'expense'],
        ['number' => '9000', 'name' => 'Saldenvorträge, Sachkonten', 'type' => 'equity'],
    ],

    // `datev_tax_key`: BU-Schlüssel des DATEV-Buchungsstapels (Steuerschlüssel-Legende des DATEV-SKR).
    'tax_codes' => [
        ['code' => 'USt19', 'name' => 'Umsatzsteuer 19 %', 'direction' => 'output', 'rate' => '19.00', 'tax_account' => '3806', 'ustva_base_field' => '81', 'datev_tax_key' => '3'],
        ['code' => 'USt7', 'name' => 'Umsatzsteuer 7 %', 'direction' => 'output', 'rate' => '7.00', 'tax_account' => '3801', 'ustva_base_field' => '86', 'datev_tax_key' => '2'],
        ['code' => 'VSt19', 'name' => 'Vorsteuer 19 %', 'direction' => 'input', 'rate' => '19.00', 'tax_account' => '1406', 'ustva_tax_field' => '66', 'datev_tax_key' => '9'],
        ['code' => 'VSt7', 'name' => 'Vorsteuer 7 %', 'direction' => 'input', 'rate' => '7.00', 'tax_account' => '1401', 'ustva_tax_field' => '66', 'datev_tax_key' => '8'],
        ['code' => 'IGL', 'name' => 'Innergemeinschaftliche Lieferung § 4 Nr. 1b UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '41', 'tax_category' => 'K'],
        // Umsätze ohne Steuer: Die Buchungs-Inbox wählt das Kennzeichen über die Steuerkategorie des Belegs.
        ['code' => 'RC21', 'name' => 'Nicht steuerbare sonstige Leistung § 18b UStG (Steuerschuld des Empfängers)', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '21', 'tax_category' => 'AE', 'datev_tax_key' => '47'],
        ['code' => 'RC60', 'name' => 'Steuerpflichtiger Umsatz, Steuerschuld des Leistungsempfängers § 13b Abs. 5 UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '60', 'tax_category' => 'AE', 'datev_tax_key' => '46'],
        ['code' => 'AUSF', 'name' => 'Steuerfreie Ausfuhrlieferung § 4 Nr. 1a UStG', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '43', 'tax_category' => 'G', 'datev_tax_key' => '1'],
        ['code' => 'NSTB', 'name' => 'Nicht steuerbarer Umsatz (Leistungsort nicht im Inland)', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'ustva_base_field' => '45', 'tax_category' => 'O'],
        ['code' => 'FREI', 'name' => 'Steuerfrei', 'direction' => 'none', 'rate' => '0.00', 'tax_account' => null, 'tax_category' => 'E'],
        // Steuerschuld des Empfängers (MVP-1621): Umsatzsteuer auf `tax_account`, Vorsteuer auf `input_tax_account`; der Rechnungseingang wählt über `tax_case`.
        ['code' => 'RC13BEU', 'name' => '§ 13b Abs. 1 UStG 19 %: sonstige Leistung aus dem übrigen Gemeinschaftsgebiet', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '3837', 'input_tax_account' => '1407', 'ustva_base_field' => '46', 'ustva_tax_field' => '47', 'ustva_input_tax_field' => '67', 'tax_case' => 'reverse_charge_eu', 'datev_tax_key' => '94'],
        ['code' => 'RC13B', 'name' => '§ 13b Abs. 2 UStG 19 %: Steuerschuld des Leistungsempfängers', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '3837', 'input_tax_account' => '1407', 'ustva_base_field' => '84', 'ustva_tax_field' => '85', 'ustva_input_tax_field' => '67', 'tax_case' => 'reverse_charge', 'datev_tax_key' => '94'],
        ['code' => 'IGE19', 'name' => 'Innergemeinschaftlicher Erwerb 19 %', 'direction' => 'both', 'rate' => '19.00', 'tax_account' => '3804', 'input_tax_account' => '1404', 'ustva_base_field' => '89', 'ustva_tax_field' => '89', 'ustva_input_tax_field' => '61', 'tax_case' => 'intra_community_acquisition', 'datev_tax_key' => '19'],
        ['code' => 'IGE7', 'name' => 'Innergemeinschaftlicher Erwerb 7 %', 'direction' => 'both', 'rate' => '7.00', 'tax_account' => '3802', 'input_tax_account' => '1402', 'ustva_base_field' => '93', 'ustva_tax_field' => '93', 'ustva_input_tax_field' => '61', 'tax_case' => 'intra_community_acquisition', 'datev_tax_key' => '18'],
    ],

    'rules' => [
        ['source_kind' => 'sales_invoice', 'role' => 'receivable', 'account' => '1200'],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4400', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4300', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4100', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        // Umsätze ohne Steuer auf das Konto ihres Kennzeichens (MVP-1622): DATEV ordnet die Kennziffer über das Konto zu.
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4125', 'match' => ['tax_code' => 'IGL']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4337', 'match' => ['tax_code' => 'RC60']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4336', 'match' => ['tax_code' => 'RC21']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4120', 'match' => ['tax_code' => 'AUSF']],
        ['source_kind' => 'sales_invoice', 'role' => 'revenue', 'account' => '4338', 'match' => ['tax_code' => 'NSTB']],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '3806', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'sales_invoice', 'role' => 'tax_output', 'account' => '3801', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        ['source_kind' => 'incoming_invoice', 'role' => 'payable', 'account' => '3300'],
        ['source_kind' => 'incoming_invoice', 'role' => 'expense', 'account' => '6300'],
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1406', 'tax_code' => 'VSt19'],
        // Mehrere Sätze je Eingangsrechnung (MVP-1622): Vorsteuer je Satz.
        ['source_kind' => 'incoming_invoice', 'role' => 'tax_input', 'account' => '1401', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'VSt7'],

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
        ['source_kind' => 'payment', 'role' => 'discount', 'account' => '4731', 'match' => ['tax_rate' => '7.00']],

        // Online-Zahlung (MVP-1067): Geldtransit bis zur Auszahlung, Gebühr als Nebenkosten des Geldverkehrs.
        ['source_kind' => 'online_payment', 'role' => 'payment_transit', 'account' => '1460'],
        ['source_kind' => 'online_payment', 'role' => 'receivable', 'account' => '1200'],
        ['source_kind' => 'online_payment', 'role' => 'payment_fees', 'account' => '6855'],
        // Angerechnete Vorauszahlung ohne lokale Rechnung (MVP-1523): erhaltene Anzahlung netto, Umsatzsteuer bei Zufluss.
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '3272', 'match' => ['tax_rate' => '19.00']],
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '3260', 'match' => ['tax_rate' => '7.00']],
        ['source_kind' => 'online_payment', 'role' => 'advance_received', 'account' => '3250', 'match' => ['tax_rate' => '0.00'], 'priority' => 90],
        ['source_kind' => 'online_payment', 'role' => 'tax_output', 'account' => '3806', 'match' => ['tax_rate' => '19.00'], 'tax_code' => 'USt19'],
        ['source_kind' => 'online_payment', 'role' => 'tax_output', 'account' => '3801', 'match' => ['tax_rate' => '7.00'], 'tax_code' => 'USt7'],

        // Jahres-AfA (Feature 133): direkte Methode, BGA als Auffangkonto —
        // Pkw/Software werden je Anlage überschrieben.
        ['source_kind' => 'depreciation', 'role' => 'fixed_asset', 'account' => '0500'],
        ['source_kind' => 'depreciation', 'role' => 'depreciation', 'account' => '6220'],

        // Anlagenabgang (MVP-891): Restbuchwert gegen Buchverlust bzw. -gewinn.
        ['source_kind' => 'asset_disposal', 'role' => 'fixed_asset', 'account' => '0500'],
        ['source_kind' => 'asset_disposal', 'role' => 'disposal_loss', 'account' => '6895'],
        ['source_kind' => 'asset_disposal', 'role' => 'disposal_gain', 'account' => '4855'],
    ],
];
