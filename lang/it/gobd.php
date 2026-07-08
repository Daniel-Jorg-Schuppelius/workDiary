<?php

/*
 * Consegna dati GoBD Z3 (Feature 063, MVP-132).
 */

return [
    'title' => 'Consegna dati GoBD (Z3)',
    'subtitle' => 'Dati fiscalmente rilevanti come pacchetto GDPdU per la verifica fiscale (leggibile da IDEA).',
    'period' => 'Periodo di verifica',
    'sections' => 'Aree dati',
    'section' => [
        'invoices' => 'Fatture emesse',
        'invoice_items' => 'Righe fattura',
        'customers' => 'Debitori',
    ],
    'preflight' => [
        'title' => 'Controllo preliminare',
        'check' => 'Verifica periodo',
        'records' => ':count record',
        'warnings' => 'Avvisi',
        'drafts' => ':count fattura/e non consolidata/e (bozza) nel periodo — non ancora definitive ai fini fiscali.',
        'empty_invoices' => 'Nessuna fattura emessa nel periodo selezionato.',
    ],
    'export' => 'Scarica pacchetto Z3',
    'recent' => [
        'title' => 'Esportazioni recenti',
        'package_hash' => 'Hash del pacchetto (SHA-256)',
        'records' => 'Record',
        'created' => 'Creato',
        'none' => 'Nessuna esportazione ancora.',
    ],
    'encoding' => 'Set di caratteri dei file di dati',
];
