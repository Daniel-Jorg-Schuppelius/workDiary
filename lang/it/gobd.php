<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gobd.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
        'time_entries' => 'Registrazioni orarie',
        'booking_batches' => 'Lotti contabili',
        'booking_batch_items' => 'Posizioni dei lotti contabili',
        'payment_allocations' => 'Abbinamenti di pagamento',
        'cash_entries' => 'Libro di cassa',
        'cash_daily_closings' => 'Chiusure di cassa giornaliere',
        'incoming_einvoices' => 'Fatture elettroniche in entrata',
        'expenses' => 'Spese',
    ],
    'preflight' => [
        'title' => 'Controllo preliminare',
        'check' => 'Verifica periodo',
        'records' => ':count record',
        'warnings' => 'Avvisi',
        'drafts' => ':count fattura/e non consolidata/e (bozza) nel periodo — non ancora definitive ai fini fiscali.',
        'draft_batches' => ':count lotto/i contabile/i non consolidato/i (bozza) nel periodo — assente/i dalla prova dei lotti contabili.',
        'empty_invoices' => 'Nessuna fattura emessa nel periodo selezionato.',
    ],
    'export' => 'Crea pacchetto Z3',
    'queued' => 'La creazione del pacchetto è in coda — la prova compare sotto e viene aggiornata al prossimo caricamento.',
    'download' => 'Scarica pacchetto',
    'recent' => [
        'status' => 'Stato',
        'actions' => 'Azioni',
        'title' => 'Esportazioni recenti',
        'package_hash' => 'Hash del pacchetto (SHA-256)',
        'records' => 'Record',
        'created' => 'Creato',
        'none' => 'Nessuna esportazione ancora.',
    ],
    'encoding' => 'Set di caratteri dei file di dati',
];
