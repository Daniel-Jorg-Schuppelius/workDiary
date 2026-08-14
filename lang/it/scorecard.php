<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scorecard.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Traduzioni (it) — Scorecard di performance fornitore (Bauturbo onda D).
 * Struttura di riferimento: lang/de/scorecard.php
 */

return [
    'title' => 'Scorecard fornitori',
    'overview_subtitle' => 'Classifica per punteggio complessivo da acquisti, magazzino, reclami e ISMS (definizione v:version).',
    'apply' => 'Mostra',
    'weights_hint' => 'Ponderazione punteggio complessivo: puntualità :ontime %, tasso di reclamo :complaints %, qualità ISMS :quality %, andamento prezzi :price %. Gli indicatori non disponibili vengono ignorati e i pesi ri-normalizzati.',
    'empty_ranking' => 'Ancora nessun fornitore con dati di acquisto, reclamo o ISMS valutabili nel periodo.',

    'chart_ranking' => 'Punteggio complessivo per fornitore (top 15)',
    'unit_score' => 'Punteggio',

    'col_supplier' => 'Fornitore',
    'col_overall' => 'Punteggio complessivo',
    'no_data' => 'nessun dato',
    'open_detail' => 'Apri dettaglio',

    'metric_ontime' => 'Puntualità',
    'metric_complaints' => 'Tasso di reclamo',
    'metric_price' => 'Andamento prezzi',
    'metric_quality' => 'Qualità ISMS',

    'detail_subtitle' => 'Scorecard da acquisti/magazzino/reclami/ISMS (definizione v:version) · :label',
    'back_to_ranking' => 'Alla classifica',
    'supplier_master' => 'Anagrafica fornitore',
    'overall_title' => 'Punteggio complessivo',
    'overall_hint' => 'Sintesi ponderata degli indicatori disponibili (0–100, più alto è meglio).',
    'goodness' => 'Punteggio :g',

    'ontime_no_source' => 'Nessuna entrata merci registrata con data di consegna promessa nel periodo.',
    'ontime_detail' => ':on consegne puntuali su :total.',
    'complaints_no_source' => 'Nessun ordine come base nel periodo.',
    'complaints_detail' => ':count reclami su :base ordini.',
    'price_no_source' => 'Nessun articolo con almeno due punti di prezzo nel periodo.',
    'price_dir_up' => 'Prezzi di acquisto in aumento.',
    'price_dir_down' => 'Prezzi di acquisto in calo.',
    'price_dir_flat' => 'Prezzi di acquisto stabili.',
    'price_dir_none' => 'Nessun andamento dei prezzi.',
    'quality_no_source' => 'Nessuna valutazione fornitore ISMS disponibile.',
    'quality_detail' => 'Classificazione di rischio ISMS attuale.',

    'drill_deliveries' => 'Entrate merci & scadenze',
    'drill_claims' => 'Reclami',
    'drill_prices' => 'Storico prezzi',

    'chart_ontime' => 'Andamento della puntualità',
    'chart_price_index' => 'Indice prezzi (base 100)',
    'chart_complaints' => 'Reclami al mese',
    'unit_percent' => 'Percento',
    'unit_index' => 'Indice',
    'unit_count' => 'Numero',
    'axis_month' => 'Mese',

    'price_articles' => 'Andamento prezzi per articolo',
    'col_article' => 'Articolo',
    'col_first_price' => 'Primo prezzo',
    'col_last_price' => 'Ultimo prezzo',
    'col_change' => 'Variazione',

    'col_order' => 'Ordine',
    'col_expected' => 'Promesso',
    'col_delivered' => 'Consegnato',
    'col_ontime_flag' => 'Scadenza',
    'pending' => 'aperto',
    'on_time' => 'puntuale',
    'late' => 'in ritardo',

    'col_claim' => 'Reclamo',
    'col_title' => 'Titolo',
    'col_reported' => 'Segnalato',
    'col_status' => 'Stato',
    'col_ordered_at' => 'Data ordine',
    'col_unit_price' => 'Prezzo unitario',
];
