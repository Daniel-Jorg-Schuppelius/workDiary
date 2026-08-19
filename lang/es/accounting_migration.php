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
    'title' => 'Cambio de software contable',
    'intro' => 'Planifica el cambio de software contable, compruébalo como simulación, asegúralo con operación paralela, conmuta en la fecha de corte y ciérralo con justificante. WorkDiary asigna ambos sistemas externos a los mismos objetos locales — los documentos finalizados nunca se reconstruyen.',
    'plan_heading' => 'Planificar cambio',
    'plan_hint' => 'Solo un cambio por organización a la vez. El análisis no escribe en ningún sistema externo.',
    'areas' => 'Áreas de datos',
    'read_only' => 'solo histórico',
    'cutover_on' => 'Fecha de corte',
    'cutover_hint' => 'A partir de ese día, los nuevos documentos de facturación se crean exclusivamente en el sistema destino; el sistema origen queda bloqueado para ello.',
    'plan_submit' => 'Planificar cambio',
    'no_cutover' => 'aún sin definir',
    'dry_run_badge' => 'Simulación',
    'run_heading' => 'Cambio :source → :target',
    'analyze' => 'Análisis (simulación)',
    'start_parallel' => 'Iniciar operación paralela',
    'cutover' => 'Conmutar',
    'cutover_confirm' => '¿Conmutar ahora? A partir de la fecha de corte, los nuevos documentos se crean exclusivamente en el sistema destino; el envío al origen queda bloqueado.',
    'complete' => 'Finalizar',
    'report' => 'Protocolo (CSV)',
    'cancel' => 'Cancelar',
    'cancel_confirm' => '¿Cancelar realmente el cambio? Las decisiones ya tomadas se conservan como justificante.',
    'blockers_heading' => 'Puntos abiertos',
    'counters_heading' => 'Contadores',
    'area' => 'Área',
    'counter_read' => 'leídos',
    'counter_matched' => 'asignados',
    'counter_pending' => 'abiertos',
    'counter_conflict' => 'conflictos',
    'items_heading' => 'Registros',
    'item_title' => 'Denominación',
    'item_source' => 'Origen',
    'item_target' => 'Destino',
    'item_status' => 'Estado',
    'item_decision' => 'Decisión',
    'history_heading' => 'Cambios anteriores',
    'status.pending' => 'abierto',
    'status.matched' => 'asignado',
    'status.transferred' => 'transferido',
    'status.conflict' => 'conflicto',
    'status.skipped' => 'omitido',
    'status.historic' => 'histórico',
    'status.failed' => 'con error',
    'source' => 'Sistema origen',
    'target' => 'Sistema destino',
];
