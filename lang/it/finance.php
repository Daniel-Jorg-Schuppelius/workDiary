<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Interfaccia finanziaria',
        'transfers' => 'Ricevute di trasferimento',
        'transfer' => 'Ricevuta di trasferimento',
        'menu' => 'Consegna fatturazione',
        'positions' => 'Posizioni generate',
        'sources' => 'Fonti singole (istantanea)',
        'events' => 'Registro eventi',
    ],

    'subtitle' => [
        'transfers' => 'Consegnare tempi e materiali fatturabili al sistema di fatturazione principale, in canali separati.',
    ],

    'field' => [
        'billing_mode' => 'Canale di fatturazione',
        'billing_mode_inherit' => '— Eredita lo standard dell\'organizzazione —',
        'billing_mode_default' => '— WorkDiary (predefinito) —',
        'billing_mode_hint' => 'Sostituisce lo standard dell\'organizzazione per questo cliente. Con Lexoffice/DATEV la fatturazione locale è bloccata.',
        'billing_mode_org_hint' => 'Canale di fatturazione predefinito dell\'organizzazione. I clienti possono sostituirlo singolarmente.',
        'channel' => 'Canale di trasferimento',
        'target' => 'Destinazione del trasferimento',
        'status' => 'Stato',
        'period' => 'Periodo di prestazione',
        'position_count' => 'Posizioni',
        'total_amount' => 'Importo totale (netto)',
        'total_quantity' => 'Quantità totale',
        'payload_hash' => 'Hash del payload',
        'transferred_at' => 'Trasferito il',
        'failure_reason' => 'Motivo dell\'errore',
        'customer' => 'Cliente',
        'source' => 'Fonte',
        'source_deleted' => 'Fonte non più disponibile',
    ],

    'action' => [
        'create_draft' => 'Preparare il trasferimento',
        'confirm' => 'Confermare il trasferimento',
        'mark_transferred' => 'Segnare come trasferito',
        'mark_failed' => 'Segnare come fallito',
        'void' => 'Annullare il trasferimento',
        'show' => 'Mostra',
        'execute' => 'Trasferire ora',
        'retry' => 'Riprova',
        'download' => 'Scaricare il pacchetto di consegna',
        'open_external' => 'Aprire esternamente',
    ],

    'filter' => [
        'all' => 'Tutti',
    ],

    'hint' => [
        'channels_separate' => 'Tempo e materiale vengono confermati come pacchetti di consegna separati.',
        'datev_desktop_api' => 'DATEV gestisce: consegna come pacchetto file (CSV) — l\'API DATEV Desktop seguirà come adattatore separato.',
        'target_by_mode' => 'La destinazione è preimpostata in base al canale di fatturazione del cliente.',
        'period_sources' => 'Vengono raccolte solo fonti fatturabili, non ancora fatturate/consegnate nel periodo.',
        'lexoffice_draft_created' => 'Bozza di fattura creata in Lexoffice:',
    ],

    'confirm_execute' => 'Trasferire ora alla destinazione? In caso di successo le fonti verranno contrassegnate come consegnate.',
    'confirm_void' => 'Annullare questo trasferimento? Le fonti verranno nuovamente liberate.',

    'empty_title' => 'Nessuna ricevuta di trasferimento',
    'empty_message' => 'Non è ancora stato preparato alcun trasferimento.',
    'empty_filtered' => 'Nessun trasferimento per i filtri selezionati.',
    'empty_positions_title' => 'Nessuna posizione',
    'empty_positions' => 'Le fonti non generano alcuna posizione (ad es. fonti eliminate).',

    'csv' => [
        'package_title' => 'Pacchetto di consegna WorkDiary (CSV) — non è un formato DATEV',
        'position' => 'Posizione',
        'date' => 'Data',
        'employee' => 'Collaboratore',
        'project' => 'Progetto/Commessa',
        'activity' => 'Attività',
        'hours' => 'Ore',
        'rate' => 'Tariffa',
        'amount' => 'Importo',
        'comment' => 'Commento',
        'product' => 'Prodotto',
        'quantity' => 'Quantità',
        'unit' => 'Unità',
        'unit_price_net' => 'Prezzo unitario netto',
        'total' => 'Totale',
    ],

    'lexoffice' => [
        'introduction' => 'Consegna da WorkDiary — :channel, periodo :from – :to.',
    ],

    'flash' => [
        'created' => 'Bozza della ricevuta di trasferimento creata.',
        'confirmed' => 'Trasferimento confermato.',
        'transferred' => 'Trasferimento completato — le fonti sono state contrassegnate come trasferite.',
        'failed' => 'Trasferimento contrassegnato come fallito.',
        'voided' => 'Trasferimento annullato — le fonti sono state nuovamente liberate.',
    ],

    'error' => [
        'local_invoicing_locked' => 'La fatturazione è gestita da :program; la creazione locale di fatture è bloccata.',
        'no_sources' => 'Nessuna fonte trasferibile trovata nel periodo selezionato.',
        'illegal_transition' => 'Il passaggio di stato da «:from» a «:to» non è consentito.',
        'void_after_transfer' => 'Un trasferimento già consegnato non può essere annullato — utilizzare un trasferimento di storno/differenza.',
        'entry_already_transferred' => 'La registrazione oraria è già stata consegnata alla fatturazione e non può più essere corretta.',
        'target_not_allowed' => 'Questa destinazione non è consentita per il canale di fatturazione «:mode».',
        'lexoffice_not_configured' => 'Lexoffice non è configurato per questa organizzazione (chiave API mancante).',
        'sources_missing' => 'Le fonti di questa ricevuta di trasferimento non sono più completamente disponibili.',
    ],
];
