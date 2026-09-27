<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : damage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Schadensfälle (MVP-919/920).
return [
    'title' => 'Sinistri',
    'subtitle' => 'Sinistri e pratiche assicurative di noleggio, leasing, reclami e veicoli — con liquidazione, franchigia e cronologia.',
    'case' => 'Sinistro',
    'empty' => 'Nessun sinistro — si creano dalla pratica (noleggio, leasing, reclamo, veicolo).',
    'nav' => [
        'section' => 'Sinistri e richiami',
        'cases' => 'Sinistri',
    ],
    'kpi' => [
        'open' => 'Pratiche aperte',
        'open_estimate' => 'Stimato (aperte)',
        'settled' => 'Liquidato',
    ],
    'filter' => [
        'all_status' => 'Tutti gli stati',
        'all_subjects' => 'Tutte le pratiche',
        'all_kinds' => 'Tutti i tipi',
    ],
    'field' => [
        'number' => 'Numero',
        'title' => 'Denominazione',
        'subject' => 'Pratica collegata',
        'kind' => 'Tipo di sinistro',
        'status' => 'Stato',
        'estimated_amount' => 'Danno stimato',
        'settled_amount' => 'Importo liquidato',
        'deductible_amount' => 'Franchigia',
        'net_recovery' => 'Rimborso al netto della franchigia',
        'currency' => 'Valuta',
        'occurred_at' => 'Data del sinistro',
        'reported_at' => 'Segnalato il',
        'insurer_name' => 'Assicuratore',
        'policy_number' => 'Numero di polizza',
        'claim_number' => 'Numero di sinistro',
        'responsible_user_id' => 'Responsabile',
        'description' => 'Dinamica',
    ],
    'section' => [
        'case' => 'Sinistro',
        'insurance' => 'Assicurazione',
        'amounts' => 'Importi',
        'status' => 'Cambia stato',
        'journal' => 'Cronologia',
    ],
    'action' => [
        'show' => 'Mostra',
        'edit' => 'Modifica',
        'save' => 'Salva',
        'open' => 'Crea sinistro',
        'report' => 'Segnala danno',
    ],
    'dialog' => [
        'create' => 'Segnala danno',
        'edit' => 'Modifica sinistro',
    ],
    'card' => [
        'title' => 'Sinistri',
        'none' => 'Nessun sinistro.',
    ],
    'status' => [
        'reported' => 'Segnalato',
        'submitted' => 'Inoltrato all\'assicuratore',
        'in_review' => 'In verifica',
        'settled' => 'Liquidato',
        'rejected' => 'Respinto',
        'closed' => 'Chiuso',
    ],
    'transition' => [
        'submitted' => 'Inoltra all\'assicuratore',
        'in_review' => 'Segna in verifica',
        'settled' => 'Registra liquidazione',
        'rejected' => 'Registra rifiuto',
        'closed' => 'Chiudi',
    ],
    'kind' => [
        'property' => 'Danno materiale',
        'liability' => 'Responsabilità civile',
        'theft' => 'Furto/smarrimento',
        'vehicle' => 'Danno al veicolo',
        'transport' => 'Danno da trasporto',
        'other' => 'Altro',
    ],
    'error' => [
        'settled_amount_required' => 'Per «liquidato» manca l\'importo liquidato.',
    ],
    'flash' => [
        'opened' => 'Sinistro :number creato.',
        'saved' => 'Sinistro salvato.',
        'status' => 'Stato: :status.',
    ],
    'subject_type' => [
        'rental_cases' => 'Noleggio',
        'asset_finance_contracts' => 'Contratto di leasing/finanziamento',
        'claim_cases' => 'Reclamo',
        'vehicles' => 'Veicolo',
    ],
];
