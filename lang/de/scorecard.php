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
 * Übersetzungen (de) — Lieferantenperformance-Scorecards (Bauturbo Welle D).
 * Referenzstruktur für lang/<code>/scorecard.php.
 */

return [
    'title' => 'Lieferanten-Scorecards',
    'overview_subtitle' => 'Ranking nach Gesamt-Score aus Einkauf, Lager, Reklamation und ISMS (Definition v:version).',
    'apply' => 'Anzeigen',
    'weights_hint' => 'Gewichtung Gesamt-Score: Termintreue :ontime %, Reklamationsquote :complaints %, ISMS-Qualität :quality %, Preisentwicklung :price %. Nicht verfügbare Kennzahlen werden übersprungen und die Gewichte re-normalisiert.',
    'empty_ranking' => 'Noch keine Lieferanten mit auswertbaren Einkaufs-, Reklamations- oder ISMS-Daten im Zeitraum.',
    'chart_ranking' => 'Gesamt-Score je Lieferant (Top 15)',
    'unit_score' => 'Score',

    'col_supplier' => 'Lieferant',
    'col_overall' => 'Gesamt-Score',
    'no_data' => 'keine Daten',
    'open_detail' => 'Detail öffnen',

    'metric_ontime' => 'Termintreue',
    'metric_complaints' => 'Reklamationsquote',
    'metric_price' => 'Preisentwicklung',
    'metric_quality' => 'ISMS-Qualität',

    'detail_subtitle' => 'Scorecard aus Einkauf/Lager/Claims/ISMS (Definition v:version) · :label',
    'back_to_ranking' => 'Zum Ranking',
    'supplier_master' => 'Lieferantenstamm',
    'overall_title' => 'Gesamt-Score',
    'overall_hint' => 'Gewichtete Zusammenfassung der verfügbaren Kennzahlen (0–100, höher ist besser).',
    'goodness' => 'Score :g',

    'ontime_no_source' => 'Keine bewerteten Wareneingänge mit zugesagtem Lieferdatum im Zeitraum.',
    'ontime_detail' => ':on von :total Lieferungen pünktlich.',
    'complaints_no_source' => 'Keine Bestellungen als Basis im Zeitraum.',
    'complaints_detail' => ':count Reklamationen auf :base Bestellungen.',
    'price_no_source' => 'Keine Artikel mit mindestens zwei Preispunkten im Zeitraum.',
    'price_dir_up' => 'Steigende Einkaufspreise.',
    'price_dir_down' => 'Sinkende Einkaufspreise.',
    'price_dir_flat' => 'Stabile Einkaufspreise.',
    'price_dir_none' => 'Keine Preisentwicklung.',
    'quality_no_source' => 'Keine ISMS-Lieferantenbewertung vorhanden.',
    'quality_detail' => 'Aktuelle ISMS-Risikoeinstufung.',

    'drill_deliveries' => 'Wareneingänge & Termine',
    'drill_claims' => 'Reklamationen',
    'drill_prices' => 'Preishistorie',

    'chart_ontime' => 'Termintreue-Verlauf',
    'chart_price_index' => 'Preisindex (Basis 100)',
    'chart_complaints' => 'Reklamationen je Monat',
    'unit_percent' => 'Prozent',
    'unit_index' => 'Index',
    'unit_count' => 'Anzahl',
    'axis_month' => 'Monat',

    'price_articles' => 'Preisentwicklung je Artikel',
    'col_article' => 'Artikel',
    'col_first_price' => 'Erstpreis',
    'col_last_price' => 'Letztpreis',
    'col_change' => 'Veränderung',

    'col_order' => 'Bestellung',
    'col_expected' => 'Zugesagt',
    'col_delivered' => 'Geliefert',
    'col_ontime_flag' => 'Termin',
    'pending' => 'offen',
    'on_time' => 'pünktlich',
    'late' => 'verspätet',

    'col_claim' => 'Reklamation',
    'col_title' => 'Titel',
    'col_reported' => 'Gemeldet',
    'col_status' => 'Status',
    'col_ordered_at' => 'Bestelldatum',
    'col_unit_price' => 'Einzelpreis',
];
