<?php
/*
 * Translations (en) — Supplier performance scorecards (Bauturbo wave D).
 * Reference structure: lang/de/scorecard.php
 */

return [
    'title' => 'Supplier scorecards',
    'overview_subtitle' => 'Ranking by overall score from purchasing, warehouse, complaints and ISMS (definition v:version).',
    'apply' => 'Show',
    'weights_hint' => 'Overall score weighting: on-time delivery :ontime %, complaint rate :complaints %, ISMS quality :quality %, price trend :price %. Unavailable metrics are skipped and the weights re-normalised.',
    'empty_ranking' => 'No suppliers with evaluable purchasing, complaint or ISMS data in this period yet.',

    'col_supplier' => 'Supplier',
    'col_overall' => 'Overall score',
    'no_data' => 'no data',
    'open_detail' => 'Open detail',

    'metric_ontime' => 'On-time delivery',
    'metric_complaints' => 'Complaint rate',
    'metric_price' => 'Price trend',
    'metric_quality' => 'ISMS quality',

    'detail_subtitle' => 'Scorecard from purchasing/warehouse/claims/ISMS (definition v:version) · :label',
    'back_to_ranking' => 'Back to ranking',
    'supplier_master' => 'Supplier record',
    'overall_title' => 'Overall score',
    'overall_hint' => 'Weighted summary of the available metrics (0–100, higher is better).',
    'goodness' => 'Score :g',

    'ontime_no_source' => 'No booked goods receipts with a promised delivery date in this period.',
    'ontime_detail' => ':on of :total deliveries on time.',
    'complaints_no_source' => 'No purchase orders as a basis in this period.',
    'complaints_detail' => ':count complaints against :base purchase orders.',
    'price_no_source' => 'No articles with at least two price points in this period.',
    'price_dir_up' => 'Rising purchase prices.',
    'price_dir_down' => 'Falling purchase prices.',
    'price_dir_flat' => 'Stable purchase prices.',
    'price_dir_none' => 'No price trend.',
    'quality_no_source' => 'No ISMS supplier assessment available.',
    'quality_detail' => 'Current ISMS risk rating.',

    'drill_deliveries' => 'Goods receipts & dates',
    'drill_claims' => 'Complaints',
    'drill_prices' => 'Price history',

    'chart_ontime' => 'On-time delivery trend',
    'chart_price_index' => 'Price index (base 100)',
    'chart_complaints' => 'Complaints per month',
    'unit_percent' => 'Percent',
    'unit_index' => 'Index',
    'unit_count' => 'Count',
    'axis_month' => 'Month',

    'price_articles' => 'Price trend per article',
    'col_article' => 'Article',
    'col_first_price' => 'First price',
    'col_last_price' => 'Last price',
    'col_change' => 'Change',

    'col_order' => 'Order',
    'col_expected' => 'Promised',
    'col_delivered' => 'Delivered',
    'col_ontime_flag' => 'Timing',
    'pending' => 'open',
    'on_time' => 'on time',
    'late' => 'late',

    'col_claim' => 'Complaint',
    'col_title' => 'Title',
    'col_reported' => 'Reported',
    'col_status' => 'Status',
    'col_ordered_at' => 'Order date',
    'col_unit_price' => 'Unit price',
];
