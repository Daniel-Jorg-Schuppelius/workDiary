<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : print.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Stampa / copisteria (MVP-459, profilo settoriale druck-kopiershop).
return [
    'document_title' => 'Dati di stampa :number',

    'nav' => [
        'section' => 'Stampa & copisteria',
    ],

    'orders' => [
        'title' => 'Ordini di stampa',
        'subtitle' => 'Ricezione dati, preflight, visto si stampi, produzione, controllo qualità e consegna — in modo riproducibile sull\'ordine di produzione.',
        'detail_title' => 'Ordine di stampa',
        'empty' => 'Nessun ordine di stampa nel periodo — i nuovi ordini si creano tramite il dialogo.',
        'kpi' => [
            'open' => 'Ordini di stampa aperti',
        ],
        'action' => [
            'create' => 'Nuovo ordine di stampa',
            'create_submit' => 'Crea ordine',
            'manufacturing' => 'Ordine di produzione',
            'bind_file' => 'Collega file',
            'run_preflight' => 'Esegui preflight',
            'override' => 'Deroga motivata',
            'manual_preflight' => 'Salva esito manuale',
            'approve' => 'Concedi visto si stampi',
            'start_production' => 'Avvia produzione',
            'resume_production' => 'Riprendi produzione',
            'quality_check' => 'Documenta CQ',
            'issue' => 'Consegna',
            'cancel' => 'Annulla',
        ],
    ],

    'section' => [
        'order' => 'Ordine',
        'file' => 'File di produzione & preflight',
        'approval' => 'Visto si stampi & snapshot',
        'production' => 'Produzione, CQ & consegna',
        'claims' => 'Reclami',
    ],

    'field' => [
        'article' => 'Articolo/prodotto di stampa',
        'quantity' => 'Quantità',
        'unit' => 'Unità',
        'customer_optional' => 'Cliente (facoltativo)',
        'walk_in' => 'Cliente di passaggio (dati minimi)',
        'due_at' => 'Scadenza',
        'output_kind' => 'Modalità di consegna',
        'files_retain_until' => 'Conservazione file fino al',
        'preflight' => 'Preflight',
        'file' => 'File',
        'file_hash' => 'Checksum (SHA-256)',
        'file_bound_at' => 'Collegato il',
        'preflight_provider' => 'Strumento di verifica',
        'preflight_at' => 'Verificato il',
        'override_reason' => 'Motivo della deroga',
        'manual_errors' => 'Errori (uno per riga)',
        'manual_warnings' => 'Avvisi (uno per riga)',
        'approved_by' => 'Approvato da',
        'approved_at' => 'Approvato il',
        'approved_file_hash' => 'Checksum approvato',
        'machine' => 'Macchina',
        'without_machine' => 'senza vincolo macchina',
        'production_started_at' => 'Avvio produzione',
        'qc_status' => 'Esito CQ',
        'qc_by' => 'CQ di',
        'qc_note' => 'Nota CQ',
        'issued_at' => 'Consegnato il',
        'handover_name' => 'Consegnato a',
        'handover_note' => 'Nota di consegna',
        'shipment' => 'Spedizione',
        'reason' => 'Motivazione',
        'good_total' => 'Quantità buona',
        'scrap_total' => 'Scarto',
        'cancel_reason' => 'Motivo dell\'annullamento',
    ],

    'snapshot' => [
        'final_format' => 'Formato finito',
        'pages' => 'Pagine',
        'orientation' => 'Orientamento',
        'bleed_mm' => 'Abbondanza (mm)',
        'safety_mm' => 'Margine di sicurezza (mm)',
        'color_mode' => 'Cromia',
        'color_profile' => 'Profilo colore',
        'spot_colors' => 'Tinte piatte',
        'material' => 'Supporto',
        'grammage' => 'Grammatura',
        'quantity' => 'Quantità',
        'due_date' => 'Scadenza',
        'finishing' => 'Finitura',
    ],

    'badge' => [
        'approval_stale' => 'File modificato — visto non valido',
        'file_purged' => 'File rimosso dopo la conservazione',
    ],

    'qc' => [
        'passed' => 'Rilasciato',
        'rework' => 'Rilavorazione',
        'blocked' => 'Bloccato',
    ],

    'hint' => [
        'retention' => 'Alla scadenza viene rimosso solo il file del cliente — ordine, snapshot e checksum restano come prova commerciale.',
        'no_snapshot' => 'Ancora nessun visto si stampi — i parametri vengono congelati come snapshot immutabile all\'approvazione.',
        'counter_minimal' => 'Vendita al banco: nessun dato personale necessario.',
        'claim_reference' => 'Il caso viene collegato all\'ordine di stampa — file approvato, snapshot di produzione ed esito CQ restano referenziabili.',
    ],

    'flash' => [
        'created' => 'Ordine di stampa creato.',
        'file_bound' => 'File di produzione collegato (checksum salvato).',
        'preflight_recorded' => 'Esito del preflight salvato.',
        'preflight_overridden' => 'Preflight derogato con motivazione.',
        'approved' => 'Visto si stampi concesso — snapshot congelato.',
        'production_started' => 'Produzione in corso.',
        'quality_checked' => 'Controllo qualità documentato.',
        'issued' => 'Ordine consegnato.',
        'cancelled' => 'Ordine annullato.',
        'claim_opened' => 'Reclamo :number creato.',
    ],

    'preflight' => [
        'file_missing' => 'Il file di produzione non è reperibile nello storage.',
        'file_empty' => 'Il file è vuoto (0 byte).',
        'mime_unexpected' => 'Tipo di file inatteso «:mime» — verificare per la stampa.',
        'pdf_header_invalid' => 'Il file è dichiarato PDF ma non ha un header PDF valido.',
    ],

    'error' => [
        'order_already_specialized' => 'Per questo ordine di produzione esiste già un ordine di stampa (1:1).',
        'order_closed' => 'L\'ordine di stampa è chiuso — il file non può più essere modificato.',
        'document_mismatch' => 'Documento/versione non coerenti o non appartenenti a questa organizzazione.',
        'file_required' => 'Collegare prima un file di produzione.',
        'provider_unsupported' => 'Lo strumento di verifica non supporta questo tipo di file.',
        'override_only_failed' => 'Solo gli errori bloccanti del preflight possono essere derogati.',
        'override_reason_required' => 'La deroga richiede una motivazione.',
        'preflight_blocks_approval' => 'Preflight in sospeso o fallito — approvazione solo dopo la verifica o una deroga motivata.',
        'parameter_required' => 'Parametro obbligatorio mancante: :parameter.',
        'approval_stale' => 'Il file è stato modificato dopo l\'approvazione — l\'ordine torna da verificare/approvare.',
        'machine_foreign' => 'La macchina non appartiene a questa organizzazione.',
        'machine_inspection_overdue' => 'Macchina con verifica/taratura obbligatoria scaduta — avvio non consentito.',
        'qc_result_invalid' => 'Esito CQ non valido.',
        'invalid_transition' => 'Cambio di stato non consentito.',
        'invalid_transition_detail' => 'Cambio di stato non consentito: :from → :to.',
        'shipment_required' => 'La consegna per spedizione richiede una spedizione esistente.',
        'handover_required' => 'Il ritiro richiede una prova di consegna (nome).',
        'cancel_reason_required' => 'L\'annullamento richiede una motivazione.',
        'file_missing_storage' => 'La versione del file non esiste nello storage.',
    ],

    // Reklamation am Druckauftrag (Issue #75).
    'claim' => [
        'title' => 'Reclamo ordine di stampa :number',
        'none' => 'Nessun reclamo per questo ordine.',
        'description' => 'Descrizione',
        'affected_quantity' => 'Quantità interessata',
        'affected_quantity_note' => 'Quantità interessata: :quantity',
        'open' => 'Crea reclamo',
    ],
];
