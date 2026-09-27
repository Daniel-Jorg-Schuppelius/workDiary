<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : contract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'template' => [
        'title' => 'Plantillas de contrato',
        'subtitle' => 'Las plantillas se crean a partir de un contrato con «Guardar como plantilla» o de un perfil sectorial.',
        'name' => 'Nombre',
        'obligations' => 'Obligaciones',
        'active' => 'Activa',
        'edit' => 'Editar plantilla',
        'delete' => 'Eliminar plantilla',
        'confirm_delete' => '¿Eliminar la plantilla «:name»? Los contratos existentes no cambian.',
        'empty_title' => 'No hay plantillas de contrato',
        'empty' => 'Abra un contrato y elija «Guardar como plantilla».',
        'use' => 'Plantilla:',
        'save_title' => 'Guardar como plantilla',
        'save' => 'Guardar plantilla',
        'save_hint' => 'Se copian tipo de contrato, título, duración, rescisión, prórroga, base de valor, regla de ajuste y obligaciones (vencimiento relativo al inicio del contrato). El socio, los importes y las fechas no.',
        'flash' => [
            'created' => 'Plantilla «:name» guardada.',
            'updated' => 'Plantilla guardada.',
            'deleted' => 'Plantilla eliminada.',
        ],
    ],
    'cost_center' => [
        'title' => 'Valores de contratos por centro de coste',
        'subtitle' => 'Contratos en curso (activos o rescindidos pero aún no finalizados); valores recurrentes llevados a año y mes, valores únicos aparte. Vista de planificación, sin asiento.',
        'field' => 'Centro de coste',
        'count' => 'Contratos',
        'yearly' => 'Anual',
        'monthly' => 'Mensual',
        'once' => 'Único',
        'none' => 'Sin centro de coste',
        'empty' => 'No hay contratos en curso.',
    ],
    'extraction' => [
        'title' => 'Sugerencias de «:document»',
        'check' => 'Los datos detectados están prerrellenados. Compárelos con el documento; solo se adoptan al guardar.',
        'none' => 'No se detectaron datos contractuales en el documento (o el texto no era legible).',
        'create' => 'Contrato desde documento',
        'leasing_create' => 'Expediente de leasing desde el documento',
        'field' => [
            'rate_amount' => 'Cuota',
            'payment_rhythm' => 'Periodicidad',
            'special_payment' => 'Pago especial',
            'residual_value' => 'Valor residual',
            'purchase_option_amount' => 'Opción de compra',
            'starts_on' => 'Inicio',
            'ends_on' => 'Fin',
            'min_term_months' => 'Duración mínima',
            'notice_period_days' => 'Plazo de preaviso (convertido a días)',
            'renew_period_months' => 'Prórroga',
            'auto_renew' => 'Prórroga automática',
            'value_amount' => 'Valor del contrato',
            'value_period' => 'Base del valor',
        ],
    ],
];
