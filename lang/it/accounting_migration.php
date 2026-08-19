<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : accounting_migration.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Buchhaltungswechsel (Feature 008/045/077, MVP-653).
return [
    'title' => 'Cambio di software contabile',
    'intro' => 'Pianifica il cambio del software contabile, verificalo come simulazione, mettilo in sicurezza con l’esercizio parallelo, commuta alla data di switch e chiudilo con attestazione. WorkDiary associa entrambi i sistemi esterni agli stessi oggetti locali — i documenti finalizzati non vengono mai ricostruiti.',
    'plan_heading' => 'Pianifica il cambio',
    'plan_hint' => 'Un solo cambio per organizzazione alla volta. L’analisi non scrive in alcun sistema esterno.',
    'areas' => 'Aree dati',
    'read_only' => 'solo storico',
    'cutover_on' => 'Data di switch',
    'cutover_hint' => 'Da quel giorno i nuovi documenti di fatturazione nascono esclusivamente nel sistema di destinazione; il sistema di origine è bloccato.',
    'plan_submit' => 'Pianifica il cambio',
    'no_cutover' => 'non ancora definita',
    'dry_run_badge' => 'Simulazione',
    'run_heading' => 'Cambio :source → :target',
    'analyze' => 'Analisi (simulazione)',
    'start_parallel' => 'Avvia esercizio parallelo',
    'cutover' => 'Commuta',
    'cutover_confirm' => 'Commutare ora? Dalla data di switch i nuovi documenti nascono esclusivamente nel sistema di destinazione; il push verso l’origine viene bloccato.',
    'complete' => 'Concludi',
    'report' => 'Protocollo (CSV)',
    'cancel' => 'Annulla',
    'cancel_confirm' => 'Annullare davvero il cambio? Le decisioni già prese restano come attestazione.',
    'blockers_heading' => 'Punti aperti',
    'counters_heading' => 'Contatori',
    'area' => 'Area',
    'counter_read' => 'letti',
    'counter_matched' => 'associati',
    'counter_pending' => 'aperti',
    'counter_conflict' => 'conflitti',
    'items_heading' => 'Record',
    'item_title' => 'Denominazione',
    'item_source' => 'Origine',
    'item_target' => 'Destinazione',
    'item_status' => 'Stato',
    'item_decision' => 'Decisione',
    'history_heading' => 'Cambi precedenti',
    'status.pending' => 'aperto',
    'status.matched' => 'associato',
    'status.transferred' => 'trasferito',
    'status.conflict' => 'conflitto',
    'status.skipped' => 'saltato',
    'status.historic' => 'storico',
    'status.failed' => 'in errore',
    'source' => 'Sistema di origine',
    'target' => 'Sistema di destinazione',
];
