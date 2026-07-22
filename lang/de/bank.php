<?php
/*
 * Created on   : Sat Jun 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bank.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'menu' => 'Zahlungsabgleich',
        'index' => 'Bankauszüge',
        'statement' => 'Bankauszug',
        'transactions' => 'Bankumsätze',
        'suggestions' => 'Zuordnungsvorschläge',
        'allocations' => 'Bestätigte Zuordnungen',
        'accounts' => 'Bankkonten',
        'account' => 'Bankkonto',
    ],
    'subtitle' => [
        'index' => 'Bankauszüge (CAMT.053/MT940) einlesen, Umsätze prüfen und offenen Rechnungen oder Spesen zuordnen.',
        'accounts' => 'Eigene Bankkonten der Organisation für die Auto-Zuordnung eingehender Auszüge.',
    ],
    'field' => [
        'format' => 'Format',
        'imported_at' => 'Importiert am',
        'imported_by' => 'Importiert von',
        'account' => 'Bankkonto',
        'period' => 'Zeitraum',
        'opening_balance' => 'Eröffnungssaldo',
        'closing_balance' => 'Schlusssaldo',
        'balance_check' => 'Saldenkette',
        'tx_count' => 'Umsätze',
        'open' => 'Offen',
        'matched' => 'Zugeordnet',
        'booking_date' => 'Buchung',
        'valuta_date' => 'Wertstellung',
        'amount' => 'Betrag',
        'direction' => 'Richtung',
        'currency' => 'Währung',
        'counterparty' => 'Gegenpartei',
        'purpose' => 'Verwendungszweck',
        'reference' => 'Referenz',
        'status' => 'Status',
        'score' => 'Trefferwert',
        'kind' => 'Art',
        'note' => 'Notiz',
        'label' => 'Bezeichnung',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Kontoinhaber',
        'datev_account_no' => 'DATEV-Kontonummer',
        'is_active' => 'Aktiv',
    ],
    'reason' => [
        'reference' => 'Rechnungsnummer',
        'amount' => 'Betrag passt',
        'skonto' => 'Skonto',
        'iban' => 'IBAN-Treffer',
        'date' => 'Datumsnähe',
        'foreign_currency' => 'Fremdwährung – manuell prüfen',
    ],
    'action' => [
        'import' => 'Bankdatei importieren',
        'upload' => 'Importieren',
        'show' => 'Anzeigen',
        'download' => 'Originaldatei herunterladen',
        'confirm' => 'Bestätigen',
        'confirm_selected' => 'Auswahl bestätigen',
        'ignore' => 'Beiseitelegen',
        'unassignable' => 'Nicht zuordenbar',
        'unmatch' => 'Zuordnung zurücknehmen',
        'manual' => 'Manuell zuordnen',
        'new_account' => 'Bankkonto anlegen',
        'edit_account' => 'Bankkonto bearbeiten',
        'delete_account' => 'Bankkonto löschen',
        'manage_accounts' => 'Bankkonten verwalten',
    ],
    'import' => [
        'dialog_title' => 'Bankdatei importieren',
        'dialog_hint' => 'CAMT.053 (XML) oder MT940. Der Import legt die Umsätze nur im Prüfbereich an und ändert keine Rechnungs- oder Spesenstatus.',
        'format_hint' => 'Unterstützte Formate: CAMT.053, MT940, OFX, QIF, QXF sowie PAIN.001/008 (Zahlungsaufträge als angekündigte Umsätze). Die Erkennung erfolgt anhand des Inhalts, nicht der Dateiendung.',
        'file' => 'Datei',
        'account_optional' => 'Bankkonto (optional, sonst Auto-Zuordnung über IBAN)',
        'flash' => [
            'imported' => ':count Umsätze importiert.',
        ],
        'error' => [
            'empty' => 'Der Auszug enthält keine Umsätze.',
            'empty_file' => 'Die Datei ist leer.',
            'duplicate_file' => 'Diese Datei wurde bereits importiert (Dublette).',
            'unavailable' => 'Der Bankimport ist ein optionales, kostenpflichtiges Zusatzmodul und in dieser Installation nicht aktiviert. Eine Freischaltung ist auf Anfrage unter :contact möglich.',
            'unavailable_badge' => 'Zusatzmodul nicht aktiviert',
        ],
    ],
    'reconcile' => [
        'flash' => [
            'confirmed' => 'Zuordnung bestätigt.',
            'ignored' => 'Umsatz beiseitegelegt.',
            'unassignable' => 'Umsatz als nicht zuordenbar markiert.',
            'unmatched' => 'Zuordnung zurückgenommen.',
        ],
        'error' => [
            'no_allocations' => 'Es wurde keine Zuordnung angegeben.',
            'target_not_found' => 'Das Zuordnungsziel wurde nicht gefunden.',
        ],
    ],
    // Sammelbuchungs-Auflösung je TransactionDetail (Toolkit-Folgepaket 2).
    'split' => [
        'title' => 'Sammelbuchung auflösen',
        'return_title' => 'Sammel-Rücklastschrift — Rückläufer je Einzeltransaktion',
        'target' => 'Posten',
        'target_placeholder' => '— Posten wählen —',
        'no_match' => 'Kein Posten gefunden',
    ],
    // Lastschrift-Rückläufer-Workflow (MVP-334).
    'return' => [
        'badge' => 'Rückläufer',
        'title' => 'Lastschrift-Rückläufer verarbeiten',
        'action' => 'Kompensieren',
        'reason_placeholder' => 'Grund (z. B. AC04)',
        'flash' => [
            'processed' => 'Rückläufer verarbeitet — ursprüngliche Zuordnung kompensiert, Posten wieder geöffnet.',
        ],
        'error' => [
            'same_transaction' => 'Die Zuordnung gehört zum Rückläufer-Umsatz selbst.',
            'not_compensatable' => 'Diese Zuordnung kann nicht kompensiert werden.',
            'already_compensated' => 'Diese Zuordnung wurde bereits kompensiert.',
        ],
        'reason' => [
            'amount' => 'Betrag passt',
            'reference' => 'Referenz-Treffer',
            'mandate' => 'Mandatsreferenz',
            'date' => 'Datumsnähe',
        ],
    ],
    'account' => [
        'flash' => [
            'created' => 'Bankkonto angelegt.',
            'updated' => 'Bankkonto aktualisiert.',
            'deleted' => 'Bankkonto gelöscht.',
        ],
        'error' => [
            'duplicate_iban' => 'Für diese IBAN existiert bereits ein Bankkonto.',
        ],
    ],
    'empty' => [
        'statements' => 'Noch keine Bankauszüge importiert.',
        'transactions' => 'Keine Umsätze in diesem Auszug.',
        'suggestions' => 'Keine Vorschläge – manuell zuordnen oder beiseitelegen.',
        'accounts' => 'Noch keine Bankkonten angelegt.',
    ],
];
