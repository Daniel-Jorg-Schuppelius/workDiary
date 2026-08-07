<?php
/*
 * Traducciones (es) — Scorecards de rendimiento de proveedor (Bauturbo ola D).
 * Estructura de referencia: lang/de/scorecard.php
 */

return [
    'title' => 'Scorecards de proveedores',
    'overview_subtitle' => 'Clasificación por puntuación global de compras, almacén, reclamaciones e ISMS (definición v:version).',
    'apply' => 'Mostrar',
    'weights_hint' => 'Ponderación de la puntuación global: puntualidad :ontime %, tasa de reclamaciones :complaints %, calidad ISMS :quality %, evolución de precios :price %. Los indicadores no disponibles se omiten y los pesos se re-normalizan.',
    'empty_ranking' => 'Aún no hay proveedores con datos de compra, reclamación o ISMS evaluables en el periodo.',

    'chart_ranking' => 'Puntuación global por proveedor (top 15)',
    'unit_score' => 'Puntuación',

    'col_supplier' => 'Proveedor',
    'col_overall' => 'Puntuación global',
    'no_data' => 'sin datos',
    'open_detail' => 'Abrir detalle',

    'metric_ontime' => 'Puntualidad',
    'metric_complaints' => 'Tasa de reclamaciones',
    'metric_price' => 'Evolución de precios',
    'metric_quality' => 'Calidad ISMS',

    'detail_subtitle' => 'Scorecard de compras/almacén/reclamaciones/ISMS (definición v:version) · :label',
    'back_to_ranking' => 'Volver a la clasificación',
    'supplier_master' => 'Ficha de proveedor',
    'overall_title' => 'Puntuación global',
    'overall_hint' => 'Resumen ponderado de los indicadores disponibles (0–100, más alto es mejor).',
    'goodness' => 'Puntuación :g',

    'ontime_no_source' => 'No hay entradas de mercancía registradas con fecha de entrega prometida en el periodo.',
    'ontime_detail' => ':on de :total entregas a tiempo.',
    'complaints_no_source' => 'No hay pedidos como base en el periodo.',
    'complaints_detail' => ':count reclamaciones sobre :base pedidos.',
    'price_no_source' => 'No hay artículos con al menos dos puntos de precio en el periodo.',
    'price_dir_up' => 'Precios de compra al alza.',
    'price_dir_down' => 'Precios de compra a la baja.',
    'price_dir_flat' => 'Precios de compra estables.',
    'price_dir_none' => 'Sin evolución de precios.',
    'quality_no_source' => 'No hay evaluación de proveedor ISMS disponible.',
    'quality_detail' => 'Clasificación de riesgo ISMS actual.',

    'drill_deliveries' => 'Entradas de mercancía y fechas',
    'drill_claims' => 'Reclamaciones',
    'drill_prices' => 'Historial de precios',

    'chart_ontime' => 'Evolución de la puntualidad',
    'chart_price_index' => 'Índice de precios (base 100)',
    'chart_complaints' => 'Reclamaciones por mes',
    'unit_percent' => 'Por ciento',
    'unit_index' => 'Índice',
    'unit_count' => 'Cantidad',
    'axis_month' => 'Mes',

    'price_articles' => 'Evolución de precios por artículo',
    'col_article' => 'Artículo',
    'col_first_price' => 'Primer precio',
    'col_last_price' => 'Último precio',
    'col_change' => 'Variación',

    'col_order' => 'Pedido',
    'col_expected' => 'Prometido',
    'col_delivered' => 'Entregado',
    'col_ontime_flag' => 'Plazo',
    'pending' => 'abierto',
    'on_time' => 'a tiempo',
    'late' => 'con retraso',

    'col_claim' => 'Reclamación',
    'col_title' => 'Título',
    'col_reported' => 'Notificado',
    'col_status' => 'Estado',
    'col_ordered_at' => 'Fecha de pedido',
    'col_unit_price' => 'Precio unitario',
];
