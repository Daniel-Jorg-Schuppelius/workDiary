<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sepa.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

return [
    'title' => 'Distinte di pagamento',
    'subtitle' => 'Bonifici e addebiti cumulativi come file SEPA',
    'empty' => 'Nessuna distinta di pagamento creata finora.',
    'no_items' => 'Nessuna posizione nella distinta.',
    'run_created' => 'Distinta di pagamento creata.',
    'run_released' => 'Distinta di pagamento approvata.',
    'run_cancelled' => 'Distinta di pagamento annullata.',
    'item_removed' => 'Posizione rimossa.',
    'item_adjusted' => 'Importo del pagamento adeguato.',
    'confirm_release' => 'Approvare la distinta con :count posizioni?',
    'confirm_cancel' => 'Annullare la distinta? Le fatture tornano pagabili.',
    'released_by' => 'Approvata da',
    'file_hash' => 'Hash del file (SHA-256)',
    'execution_hint' => 'Data proposta; la banca esegue non prima di quel giorno.',
    'discount_used' => 'Sconto :percent %',
    'adjust_hint' => 'Importo fatturato: :gross. Un pagamento inferiore richiede un motivo.',
    'reference' => 'Fattura :number',
    'reference_unknown' => 'Fattura senza numero',
    'document_description' => 'File SEPA della distinta :id',

    'proposal' => [
        'title' => 'Proposta di pagamento',
        'subtitle' => 'Fatture passive approvate con la data di esecuzione più conveniente',
        'empty' => 'Nessuna fattura aperta approvata per il pagamento.',
    ],

    'action' => [
        'confirm_iban' => 'Conferma IBAN',
        'proposal' => 'Proposta di pagamento',
        'create_run' => 'Crea distinta',
        'show' => 'Visualizza',
        'release' => 'Approva',
        'export' => 'File SEPA',
        'cancel' => 'Annulla',
        'adjust' => 'Adegua importo',
        'remove_item' => 'Rimuovi posizione',
    ],

    'column' => [
        'label' => 'Denominazione',
        'kind' => 'Tipo',
        'account' => 'Conto bancario',
        'execution_date' => 'Esecuzione',
        'positions' => 'Posizioni',
        'total' => 'Totale',
        'status' => 'Stato',
        'creditor' => 'Beneficiario',
        'invoice_number' => 'Fattura',
        'due_date' => 'Scadenza',
        'execute_on' => 'Pagare il',
        'gross' => 'Importo fatturato',
        'amount' => 'Importo da pagare',
        'note' => 'Nota',
        'reference' => 'Causale',
        'deduction' => 'Detrazione',
    ],

    'status' => [
        'draft' => 'Bozza',
        'released' => 'approvata',
        'exported' => 'esportata',
        'cancelled' => 'annullata',
    ],

    'iban_confirmed' => 'L’IBAN divergente è stato confermato — la posizione è ora pagabile.',

    'blocked' => [
        'missing_iban' => 'IBAN mancante',
        'zero_amount' => 'Importo 0',
        'iban_differs' => 'IBAN diverso dall’anagrafica',
    ],

    'error' => [
        'no_iban_deviation' => 'L’IBAN della fattura non differisce (più) dall’anagrafica del fornitore.',
        'no_positions' => 'La distinta non contiene posizioni.',
        'not_draft' => 'La distinta non è più una bozza.',
        'not_released' => 'La distinta non è approvata.',
        'four_eyes' => 'Principio dei quattro occhi: chi ha preparato la distinta non può approvarla da solo.',
        'exported_final' => 'Una distinta esportata non viene più annullata.',
        'invalid_amount' => 'L’importo da pagare deve essere superiore a 0 e non può superare l’importo fatturato.',
        'reason_required' => 'Un importo ridotto richiede un motivo.',
        'zero_amount' => 'L’importo deve essere superiore a 0.',
        'account_without_iban' => 'Per il conto bancario scelto non è registrato alcun IBAN.',
        'missing_creditor_id' => 'Non è registrato alcun identificativo creditore (impostazione finance.sepa_creditor_id).',
        'mandate_unusable' => 'Il mandato è revocato o inutilizzato da oltre 36 mesi.',
        'item_without_mandate' => 'Una posizione di addebito senza mandato non può essere esportata.',
        'unavailable' => 'L’esportazione SEPA non è abilitata in questa installazione. Attivazione tramite :contact.',
    ],

    'mandate' => [
        'title' => 'Mandati SEPA',
        'subtitle' => 'Mandati di addebito dei clienti',
        'empty' => 'Nessun mandato registrato finora.',
        'created' => 'Mandato creato.',
        'revoked' => 'Mandato revocato.',
        'confirm_revoke' => 'Revocare il mandato? Da quel momento l’addebito non è più consentito.',
        'not_usable' => 'non addebitabile',
        'reference_hint' => 'Univoco per creditore; compare sull’estratto conto del cliente.',

        'action' => [
            'create' => 'Registra mandato',
            'revoke' => 'Revoca',
        ],

        'column' => [
            'reference' => 'Riferimento mandato',
            'customer' => 'Cliente',
            'kind' => 'Tipo',
            'signed_on' => 'Firmato il',
            'last_collected_on' => 'Ultimo addebito',
            'status' => 'Stato',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'account_holder' => 'Intestatario',
            'note' => 'Nota',
        ],
    ],
];
