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
    // Verbraucherpreisindex und Indexanpassung (MVP-952).
    'price_index' => [
        'title' => 'Índice de precios al consumo',
        'subtitle' => 'IPC de Alemania (base 2020 = 100) de la serie del Bundesbank. Los valores nuevos o revisados solo se aplican tras su aprobación.',
        'pending' => 'Pendiente de aprobación: :count valores',
        'add' => 'Añadir valor',
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'empty' => 'Aún no hay valores del índice. La importación se ejecuta mensualmente (contracts:price-index-sync).',
        'field' => [
            'period' => 'Mes',
            'value' => 'Valor del índice',
            'source' => 'Fuente',
        ],
        'source' => [
            'bundesbank' => 'Bundesbank',
            'manual' => 'Manual',
        ],
        'status' => [
            'pending' => 'Aprobación pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ],
        'flash' => [
            'approved' => 'Valor del índice aprobado.',
            'rejected' => 'Valor del índice rechazado.',
            'saved' => 'Valor del índice guardado y aprobado.',
        ],
    ],
    'indexation' => [
        'title' => 'Ajuste por índice (IPC)',
        'configure' => 'Cláusula de indexación',
        'check' => 'Comprobar ahora',
        'save' => 'Guardar',
        'apply' => 'Aplicar',
        'dismiss' => 'Descartar',
        'not_configured' => 'No hay índice base. Introduzca el índice y el mes base de la cláusula de indexación.',
        'base_line' => 'Índice base :value (:period)',
        'preview_line' => 'actualmente :value (:period), :change % → :amount :currency',
        'effective' => 'efectivo desde :date',
        'confirm_apply' => '¿Fijar el valor del contrato en :amount y actualizar el índice base?',
        'superseded' => 'Sustituido por un valor del índice más reciente.',
        'notification' => 'Ajuste por índice propuesto: :number',
        'disclaimer' => 'El cálculo sigue los datos guardados. No sustituye la revisión de la cláusula de indexación.',
        'section' => [
            'base' => 'Base y regla',
        ],
        'field' => [
            'base_value' => 'Índice base',
            'base_period' => 'Mes base',
            'threshold' => 'Umbral (%)',
            'pass_through' => 'Repercusión (%)',
            'index' => 'Índice',
            'change' => 'Variación',
            'old_amount' => 'Anterior',
            'new_amount' => 'Nuevo',
        ],
        'hint' => [
            'base_value' => 'Nivel del índice al firmar o en el último ajuste, base 2020 = 100.',
            'threshold' => 'Ajuste solo a partir de esta variación, vacío = cualquier variación.',
            'pass_through' => 'Parte de la variación que se repercute, vacío = 100 %.',
        ],
        'status' => [
            'proposed' => 'Propuesto',
            'applied' => 'Aplicado',
            'dismissed' => 'Descartado',
        ],
        'flash' => [
            'saved' => 'Cláusula de indexación guardada.',
            'proposed' => 'Propuesta de ajuste creada.',
            'none' => 'Sin propuesta: no hay un valor aprobado nuevo o no se alcanza el umbral.',
            'applied' => 'Ajuste por índice aplicado.',
            'dismissed' => 'Ajuste por índice descartado.',
        ],
    ],
];
