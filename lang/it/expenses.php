<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : expenses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'receipt' => [
        'no_vendor' => 'Senza fornitore',
        'link_title' => 'Documento contabile',
        'link' => 'Collega',
        'unlink' => 'Rimuovi collegamento',
        'unlink_confirm' => 'Rimuovere il collegamento al documento contabile? La nota spese tornerà a contare come costo proprio.',
        'suggestions_hint' => 'Documenti con lo stesso importo nella finestra temporale. Il collegamento conferma che si tratta della stessa operazione — la nota spese non conta più due volte.',
        'no_suggestions' => 'Nessun documento corrispondente',
        'no_suggestions_hint' => 'Senza collegamento la nota spese viene esposta separatamente come costo interno.',
        'no_provider' => 'Nessuna contabilità collegata',
        'no_provider_hint' => 'Senza un sistema contabile collegato non ci sono né proposte di documenti né trasferimento — la nota spese viene esposta separatamente come costo interno.',
        'linked' => 'Documento :number collegato.',
        'unlinked' => 'Collegamento rimosso.',
        'title' => 'File del giustificativo',
        'hint' => 'Allega la ricevuta alla nota spese — senza di essa non è verificabile né trasferibile alla contabilità.',
    ],
    'title' => [
        'index' => 'Spese',
        'create' => 'Registra spesa',
        'edit' => 'Modifica spesa',
        'inbox' => 'Approvazione spese',
        'category_index' => 'Categorie di spesa',
        'category_create' => 'Crea categoria di spesa',
        'category_edit' => 'Modifica categoria di spesa',
    ],
    'intro' => [
        'category' => 'Le categorie di spesa raggruppano i giustificativi (es. vitto, alloggio, ospitalità) e definiscono valori predefiniti come l\'aliquota fiscale, l\'obbligo di caricare un giustificativo e se la spesa è per impostazione predefinita rifatturabile al cliente. Icona e colore definiscono l\'aspetto in elenchi e report.',
    ],
    'field' => [
        'label' => 'Etichetta',
        'slug' => 'Slug',
        'icon' => 'Icona (material symbol)',
        'color' => 'Colore',
        'description' => 'Descrizione',
        'sort' => 'Ordine',
        'is_active' => 'Attivo',
        'default_tax_rate' => 'Aliquota fiscale (predefinita, %)',
        'requires_receipt' => 'Giustificativo obbligatorio',
        'default_billable' => 'Rifatturabile al cliente per impostazione predefinita',
        'date' => 'Data del giustificativo',
        'category' => 'Categoria',
        'vendor' => 'Fornitore',
        'amount_gross' => 'Importo lordo',
        'amount_net' => 'Importo netto',
        'tax_rate' => 'Aliquota fiscale (%)',
        'tax_amount' => 'Importo imposta',
        'currency' => 'Valuta',
        'payment_method' => 'Metodo di pagamento',
        'project' => 'Progetto',
        'customer' => 'Cliente',
        'task' => 'Attività',
        'billable' => 'Rifatturabile al cliente',
        'notes' => 'Note',
        'status' => 'Stato',
        'attachments' => 'Giustificativi',
        'reimbursement_reference' => 'Riferimento di rimborso',
        'reject_reason' => 'Motivo del rifiuto',
        'decided_at' => 'Deciso il',
        'reimbursed_at' => 'Rimborsato il',
    ],
    'action' => [
        'create_category' => 'Crea categoria',
        'create' => 'Registra spesa',
        'submit' => 'Invia per l\'approvazione',
        'approve' => 'Approva',
        'reject' => 'Rifiuta',
        'cancel' => 'Annulla',
        'reimburse' => 'Segna come rimborsato',
        'export' => 'Esporta CSV',
    ],
    'help' => [
        'color' => 'Definisce il colore d\'accento per icona, badge ed evidenziazioni negli elenchi.',
        'gross_first' => 'Inserisci l\'importo lordo dal giustificativo. Importo netto e imposta vengono calcolati automaticamente.',
        'requires_receipt' => 'Se attivo, è richiesto almeno un giustificativo (foto/PDF) durante la registrazione.',
    ],
    'empty' => [
        'categories' => 'Ancora nessuna categoria di spesa.',
        'expenses' => 'Ancora nessuna spesa registrata.',
    ],
    'confirm' => [
        'delete_category' => 'Eliminare davvero questa categoria di spesa?',
        'delete_expense' => 'Eliminare davvero questa spesa?',
    ],
];
