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

// Registro rivendita (funzionalità 152): messaggi dei lettori di importazione (CSV Telekom,
// XLSX/PDF Quality Hosting, elenco generico). Solo nomi di file, mai percorsi.
return [
    'column' => [
        'company' => 'Azienda',
        'product' => 'Prodotto',
        'start' => 'Inizio',
    ],
    'file' => [
        'unreadable' => 'File non leggibile: :file',
        'xlsx_unreadable' => 'File XLSX non leggibile: :file (:reason)',
        'no_header' => 'CSV senza riga di intestazione: :file',
        'no_sheet' => 'XLSX senza foglio di lavoro: :file',
        'missing_columns' => 'Colonne obbligatorie mancanti: :columns',
    ],
    'row' => [
        'too_many_fields' => 'Riga :line: :found campi invece di :expected (separatore non mascherato?) - saltata.',
        'missing_company_or_product' => 'Riga :line: azienda o prodotto mancante - saltata.',
        'missing_company_or_entitlement' => 'Riga :line: azienda o entitlement mancante - saltata.',
        'start_unreadable' => 'Riga :line (:company): inizio ":value" non leggibile - saltata.',
        'end_unreadable' => 'Riga :line (:company): fine ":value" non leggibile - saltata.',
        'end_before_start' => 'Riga :line (:company): la fine non è successiva all\'inizio - saltata.',
        'unknown_frequency' => 'Riga :line (:company): periodicità sconosciuta ":value" - saltata.',
        'quantity_invalid' => 'Riga :line (:company): quantità ":value" non leggibile o non un intero positivo - saltata.',
        'unknown_currency' => 'Riga :line (:company): valuta sconosciuta ":value" - saltata.',
        'duplicate_id' => 'Riga :line (:company): identificativo ":value" duplicato rispetto alla riga :other - saltata.',
        'term_unreadable' => 'Riga :line (:company): durata ":value" non leggibile - usata la durata standard della periodicità.',
        'fee_unreadable' => 'Riga :line (:company): canone ":value" non leggibile - saltata.',
        'dates_unreadable' => 'Riga :line (:company): data non leggibile (":start" / ":end") - saltata.',
        'contract_end_before_start' => 'Riga :line (:company): la fine del contratto non è successiva all\'inizio - saltata.',
        'no_contract' => 'Riga :line (:company): senza numero di contratto - saltata.',
        'total_unreadable' => 'Riga :line (:company): prezzo totale ":value" non leggibile - saltata.',
        'contract_start_unreadable' => 'Riga :line (:company): inizio contratto ":value" non leggibile - saltata.',
        'status_end_before_start' => 'Riga :line (:company): la fine contratto ricavata dallo stato non è successiva all\'inizio - saltata.',
        'status_unknown' => 'Riga :line (:company): stato contratto ":value" sconosciuto - trattato come in corso.',
    ],
    'pricelist' => [
        'unreadable' => 'Listino prezzi non leggibile: :file',
        'unreadable_reason' => 'Listino prezzi non leggibile: :file (:reason)',
        'no_sheet' => 'Listino prezzi senza foglio "Preisdaten" (colonna "Produkttarif" mancante).',
        'missing_columns' => 'Colonne obbligatorie del listino mancanti: :columns',
        'row_invalid' => 'Listino riga :line (:product): durata, intervallo o prezzo non leggibile - saltata.',
        'valid_from_unreadable' => 'Listino riga :line (:product): "Gültig ab" ":value" non leggibile - validità dal frontespizio o dalla data di importazione.',
        'no_valid_from' => 'Listino senza data di validità (né frontespizio né colonna "Gültig ab") - la data di importazione vale come inizio validità.',
    ],
    'invoice' => [
        'unreadable' => 'File PDF :file non leggibile (:reason).',
        'no_text' => 'Da :file non è stato possibile estrarre testo (nemmeno tramite OCR).',
        'no_number' => 'Nessun numero di fattura/nota di credito trovato.',
        'no_date' => 'Nessuna data del documento trovata.',
        'total_mismatch' => 'La somma delle posizioni (:lines) differisce dall\'importo netto del documento (:net) - mancano posizioni o sono state suddivise diversamente.',
    ],
];
