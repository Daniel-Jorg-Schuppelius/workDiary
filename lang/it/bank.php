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
        'menu' => 'Riconciliazione dei pagamenti',
        'index' => 'Estratti conto',
        'statement' => 'Estratto conto',
        'transactions' => 'Movimenti bancari',
        'suggestions' => 'Proposte di assegnazione',
        'allocations' => 'Assegnazioni confermate',
        'accounts' => 'Conti bancari',
        'account' => 'Conto bancario',
    ],
    'subtitle' => [
        'index' => 'Importare estratti conto (CAMT.053/MT940), verificare i movimenti e assegnarli a fatture o spese aperte.',
        'accounts' => 'Conti bancari dell’organizzazione per l’assegnazione automatica degli estratti in entrata.',
    ],
    'field' => [
        'format' => 'Formato',
        'imported_at' => 'Importato il',
        'imported_by' => 'Importato da',
        'account' => 'Conto bancario',
        'period' => 'Periodo',
        'opening_balance' => 'Saldo iniziale',
        'closing_balance' => 'Saldo finale',
        'balance_check' => 'Catena dei saldi',
        'tx_count' => 'Movimenti',
        'open' => 'Aperto',
        'matched' => 'Assegnato',
        'booking_date' => 'Contabilizzazione',
        'valuta_date' => 'Data valuta',
        'amount' => 'Importo',
        'direction' => 'Direzione',
        'currency' => 'Valuta',
        'counterparty' => 'Controparte',
        'purpose' => 'Causale',
        'reference' => 'Riferimento',
        'status' => 'Stato',
        'score' => 'Punteggio',
        'kind' => 'Tipo',
        'note' => 'Nota',
        'label' => 'Etichetta',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Intestatario del conto',
        'datev_account_no' => 'N. conto DATEV',
        'is_active' => 'Attivo',
    ],
    'reason' => [
        'reference' => 'Numero fattura',
        'amount' => 'Importo corrisponde',
        'skonto' => 'Sconto',
        'iban' => 'Corrispondenza IBAN',
        'date' => 'Prossimità di data',
        'foreign_currency' => 'Valuta estera – verificare manualmente',
    ],
    'action' => [
        'import' => 'Importa file bancario',
        'upload' => 'Importa',
        'show' => 'Mostra',
        'download' => 'Scarica file originale',
        'confirm' => 'Conferma',
        'confirm_selected' => 'Conferma selezione',
        'ignore' => 'Accantona',
        'unassignable' => 'Non assegnabile',
        'unmatch' => 'Annulla assegnazione',
        'manual' => 'Assegna manualmente',
        'new_account' => 'Aggiungi conto bancario',
        'edit_account' => 'Modifica conto bancario',
        'delete_account' => 'Elimina conto bancario',
        'manage_accounts' => 'Gestisci conti bancari',
    ],
    'import' => [
        'dialog_title' => 'Importa file bancario',
        'dialog_hint' => 'CAMT.053 (XML) o MT940. L’importazione crea i movimenti solo nell’area di verifica e non modifica alcuno stato di fattura o spesa.',
        'format_hint' => 'Formati supportati: CAMT.053, MT940, OFX, QIF, QXF e PAIN.001/008 (ordini di pagamento come movimenti annunciati). Il riconoscimento avviene in base al contenuto, non all’estensione del file.',
        'file' => 'File',
        'account_optional' => 'Conto bancario (facoltativo, altrimenti assegnazione automatica tramite IBAN)',
        'flash' => [
            'imported' => ':count movimenti importati.',
        ],
        'error' => [
            'empty' => 'L’estratto conto non contiene movimenti.',
            'empty_file' => 'Il file è vuoto.',
            'duplicate_file' => 'Questo file è già stato importato (duplicato).',
            'unavailable' => 'L’importazione bancaria è un modulo aggiuntivo opzionale e a pagamento, non attivato in questa installazione. L’attivazione è possibile su richiesta a :contact.',
        ],
    ],
    'reconcile' => [
        'flash' => [
            'confirmed' => 'Assegnazione confermata.',
            'ignored' => 'Movimento accantonato.',
            'unassignable' => 'Movimento contrassegnato come non assegnabile.',
            'unmatched' => 'Assegnazione annullata.',
        ],
        'error' => [
            'no_allocations' => 'Nessuna assegnazione indicata.',
            'target_not_found' => 'Destinazione dell’assegnazione non trovata.',
        ],
    ],
    // Lastschrift-Rückläufer-Workflow (MVP-334).
    'return' => [
        'badge' => 'Storno',
        'title' => 'Elabora storno di addebito',
        'action' => 'Compensa',
        'reason_placeholder' => 'Motivo (es. AC04)',
        'flash' => [
            'processed' => 'Storno elaborato — assegnazione originale compensata, posta riaperta.',
        ],
        'error' => [
            'same_transaction' => 'L’assegnazione appartiene allo stesso movimento di storno.',
            'not_compensatable' => 'Questa assegnazione non può essere compensata.',
            'already_compensated' => 'Questa assegnazione è già stata compensata.',
        ],
        'reason' => [
            'amount' => 'Importo corrispondente',
            'reference' => 'Riferimento corrispondente',
            'mandate' => 'Riferimento del mandato',
            'date' => 'Prossimità di data',
        ],
    ],
    'account' => [
        'flash' => [
            'created' => 'Conto bancario creato.',
            'updated' => 'Conto bancario aggiornato.',
            'deleted' => 'Conto bancario eliminato.',
        ],
        'error' => [
            'duplicate_iban' => 'Esiste già un conto bancario per questo IBAN.',
        ],
    ],
    'empty' => [
        'statements' => 'Nessun estratto conto importato finora.',
        'transactions' => 'Nessun movimento in questo estratto conto.',
        'suggestions' => 'Nessuna proposta – assegna manualmente o accantona.',
        'accounts' => 'Nessun conto bancario creato finora.',
    ],
];
