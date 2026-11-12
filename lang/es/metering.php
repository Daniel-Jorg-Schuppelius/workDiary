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
    'not_runnable' => 'Este acuerdo no se factura (estado: :status). Para facturarlo, cambie el estado a «Activo».',
    'project_hint' => 'Los borradores de factura del proceso se asignan a este proyecto.',
    'status_hint' => 'El proceso omite los acuerdos en pausa y finalizados. Tras una pausa, recupera desde «Próxima facturación»; adelante la fecha si es necesario.',
    'tiers' => [
        'legend' => 'Escala por tramos',
        'hint' => 'Opcional. Con escala, en cada tramo se aplica su precio en lugar del precio unitario; el cálculo es progresivo sobre el consumo una vez descontada la cantidad gratuita. El primer tramo empieza en 0; las filas vacías se ignoran.',
        'from' => 'Tramo :n desde cantidad',
        'price' => 'Tramo :n precio por unidad',
        'badge' => 'Escala (:count tramos)',
        'badge_title' => 'Se aplica una escala por tramos en lugar del precio unitario.',
        'first_from_zero' => 'El primer tramo debe empezar en 0; de lo contrario, parte del consumo quedaría sin facturar.',
        'duplicate_from' => 'Cada tramo necesita su propia cantidad «desde».',
    ],
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
        'project' => 'Proyecto',
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
