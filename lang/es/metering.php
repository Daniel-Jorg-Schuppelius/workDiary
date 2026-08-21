<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Zählerstands-Faktura (Feature 116, MVP-605).
return [
    'title' => 'Facturación por contador',
    'subtitle' => 'Facturación por consumo por cliente y equipo a partir de las lecturas',
    'empty' => 'Aún no hay ningún acuerdo registrado.',
    'created' => 'Acuerdo registrado.',
    'updated' => 'Acuerdo actualizado.',
    'draft_notice' => 'El proceso solo crea borradores de factura — la revisión y la emisión siguen siendo manuales.',
    'blocked_external' => 'Un sistema externo lleva la facturación de este cliente — no se crea ningún documento.',
    'run_done' => 'Facturado: :created borrador(es), :skipped omitidos.',
    'form_hint' => 'Sin lectura final en el periodo no se crea un borrador, sino un aviso — no se estima nada.',
    'unit_default' => 'unidades',
    'action' => [
        'create' => 'Registrar acuerdo',
        'edit' => 'Editar acuerdo',
        'run' => 'Facturar ahora',
    ],
    'column' => [
        'title' => 'Denominación',
        'customer' => 'Cliente',
        'asset' => 'Equipo',
        'base_price' => 'Precio base',
        'unit_price' => 'Precio unitario',
        'free_units' => 'Cantidad incluida',
        'unit' => 'Unidad',
        'interval' => 'Periodicidad',
        'interval_count' => 'Factor',
        'next_run_on' => 'Próxima facturación',
        'end_on' => 'Fin',
        'status' => 'Estado',
    ],
    'interval' => [
        'monthly' => 'mensual',
        'quarterly' => 'trimestral',
        'yearly' => 'anual',
    ],
    'status' => [
        'active' => 'Activo',
        'paused' => 'En pausa',
        'ended' => 'Finalizado',
    ],
    'skipped' => [
        'heading' => 'Facturaciones omitidas',
        'hint' => 'Sin lectura no hay factura. Añada la lectura y vuelva a facturar.',
        'reason' => [
            'missing_start_reading' => 'Sin lectura inicial antes del periodo',
            'missing_end_reading' => 'Sin lectura dentro del periodo',
            'negative_consumption' => 'Consumo negativo (¿cambio de contador?)',
            'nothing_to_bill' => 'Ni consumo ni precio base',
        ],
    ],
    'line' => [
        'base' => ':title — precio base del :from al :to',
        'usage' => ':title — consumo :consumption :unit, de los cuales :free incluidos',
        'estimated' => '(lectura estimada)',
    ],
];
