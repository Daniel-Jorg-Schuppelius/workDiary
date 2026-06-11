<?php
/*
 * Stringhe per il modulo di whistleblowing (categorie ecc.).
 */

return [
    'category' => [
        'corruption' => 'Corruzione e concussione',
        'fraud' => 'Frode, appropriazione indebita e furto',
        'money_laundering' => 'Riciclaggio di denaro e finanziamento del terrorismo',
        'procurement' => 'Violazioni in materia di appalti e concorrenza',
        'data_protection' => 'Protezione dei dati e sicurezza delle informazioni',
        'product_safety' => 'Sicurezza dei prodotti e tutela dei consumatori',
        'environment' => 'Violazioni ambientali e della sicurezza sul lavoro',
        'discrimination' => 'Discriminazione, molestie e abuso di potere',
        'policy_violation' => 'Violazione delle direttive interne',
        'other' => 'Altra possibile violazione di legge',
    ],
    'status' => [
        'submitted' => 'Ricevuta',
        'acknowledged' => 'Ricezione confermata',
        'triage' => 'Valutazione preliminare',
        'investigating' => 'In lavorazione',
        'waiting_reporter' => 'In attesa del segnalante',
        'referred' => 'Trasmessa',
        'closed_substantiated' => 'Chiusa – fondata',
        'closed_unsubstantiated' => 'Chiusa – non fondata',
        'closed_out_of_scope' => 'Chiusa – fuori ambito di applicazione',
        'closed_duplicate' => 'Chiusa – duplicato',
        'retention_review' => 'Verifica di conservazione',
        'legal_hold' => 'Blocco della cancellazione (legal hold)',
        'deleted' => 'Cancellata',
    ],
    'reporter_status' => [
        'received' => 'Ricevuta e in fase di verifica',
        'in_progress' => 'In lavorazione',
        'awaiting_you' => 'In attesa di un Suo riscontro',
        'closed' => 'Chiusa',
    ],
];
