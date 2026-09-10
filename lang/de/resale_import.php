<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Reselling-Register (Feature 152): Meldungen der Import-Reader (Telekom-CSV,
// Quality-Hosting-XLSX/PDF, generische Liste). Nur Dateinamen, nie Pfade.
return [
    'column' => [
        'company' => 'Firma',
        'product' => 'Produkt',
        'start' => 'Beginn',
    ],
    'file' => [
        'unreadable' => 'Datei nicht lesbar: :file',
        'xlsx_unreadable' => 'XLSX-Datei nicht lesbar: :file (:reason)',
        'no_header' => 'CSV ohne Kopfzeile: :file',
        'no_sheet' => 'XLSX ohne Tabellenblatt: :file',
        'missing_columns' => 'Pflichtspalten fehlen: :columns',
    ],
    'row' => [
        'too_many_fields' => 'Zeile :line: :found Felder statt :expected (unmaskiertes Trennzeichen?) - übersprungen.',
        'missing_company_or_product' => 'Zeile :line: Firma oder Produkt fehlt - übersprungen.',
        'missing_company_or_entitlement' => 'Zeile :line: Firma oder Entitlement fehlt - übersprungen.',
        'start_unreadable' => 'Zeile :line (:company): Beginn ":value" nicht lesbar - übersprungen.',
        'end_unreadable' => 'Zeile :line (:company): Ende ":value" nicht lesbar - übersprungen.',
        'end_before_start' => 'Zeile :line (:company): Ende liegt nicht nach dem Beginn - übersprungen.',
        'unknown_frequency' => 'Zeile :line (:company): unbekannter Rhythmus ":value" - übersprungen.',
        'quantity_invalid' => 'Zeile :line (:company): Menge ":value" nicht lesbar oder keine positive Ganzzahl - übersprungen.',
        'unknown_currency' => 'Zeile :line (:company): unbekannte Währung ":value" - übersprungen.',
        'duplicate_id' => 'Zeile :line (:company): Kennung ":value" doppelt zu Zeile :other - übersprungen.',
        'term_unreadable' => 'Zeile :line (:company): Laufzeit ":value" nicht lesbar - Standardlaufzeit des Rhythmus verwendet.',
        'fee_unreadable' => 'Zeile :line (:company): Gebühr ":value" nicht lesbar - übersprungen.',
        'dates_unreadable' => 'Zeile :line (:company): Datum nicht lesbar (":start" / ":end") - übersprungen.',
        'contract_end_before_start' => 'Zeile :line (:company): Vertragsende liegt nicht nach dem Beginn - übersprungen.',
        'no_contract' => 'Zeile :line (:company): ohne Vertragsnummer - übersprungen.',
        'total_unreadable' => 'Zeile :line (:company): Gesamtpreis ":value" nicht lesbar - übersprungen.',
        'contract_start_unreadable' => 'Zeile :line (:company): Vertragsstart ":value" nicht lesbar - übersprungen.',
        'status_end_before_start' => 'Zeile :line (:company): Vertragsende aus Status liegt nicht nach dem Beginn - übersprungen.',
        'status_unknown' => 'Zeile :line (:company): Vertragsstatus ":value" unbekannt - als laufend behandelt.',
    ],
    'pricelist' => [
        'unreadable' => 'Preisliste nicht lesbar: :file',
        'unreadable_reason' => 'Preisliste nicht lesbar: :file (:reason)',
        'no_sheet' => 'Preisliste ohne Blatt „Preisdaten" (Spalte „Produkttarif" fehlt).',
        'missing_columns' => 'Pflichtspalten der Preisliste fehlen: :columns',
        'row_invalid' => 'Preisliste Zeile :line (:product): Laufzeit, Intervall oder Preis nicht lesbar - übersprungen.',
        'valid_from_unreadable' => 'Preisliste Zeile :line (:product): „Gültig ab" ":value" nicht lesbar - Gültigkeit vom Deckblatt bzw. Importdatum.',
        'no_valid_from' => 'Preisliste ohne Gültigkeitsdatum (weder Deckblatt noch Spalte „Gültig ab") - das Importdatum gilt als Gültigkeitsbeginn.',
    ],
    'invoice' => [
        'unreadable' => 'PDF-Datei :file nicht lesbar (:reason).',
        'no_text' => 'Aus :file ließ sich kein Text gewinnen (auch nicht per OCR).',
        'no_number' => 'Keine Rechnungs-/Gutschriftsnummer gefunden.',
        'no_date' => 'Kein Belegdatum gefunden.',
        'total_mismatch' => 'Summe der Positionen (:lines) weicht vom Nettobetrag des Belegs (:net) ab - Positionen fehlen oder wurden anders zerlegt.',
    ],
];
