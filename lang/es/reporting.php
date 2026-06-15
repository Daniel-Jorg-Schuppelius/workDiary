<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reporting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'target' => [
        'nav' => 'Valores objetivo',
        'title' => 'Valores objetivo & referencias',
        'subtitle' => 'Define valores objetivo por indicador – los informes muestran objetivo, real y la desviación.',
        'create' => 'Añadir valor objetivo',
        'edit' => 'Editar valor objetivo',
        'empty' => 'Aún no hay valores objetivo definidos.',
        'metric_label' => 'Indicador',
        'scope_label' => 'Ámbito',
        'scope_ref' => 'Objeto de referencia',
        'scope_ref_hint' => 'Seleccionar solo para cliente/proyecto/empleado.',
        'value_label' => 'Valor objetivo',
        'period_label' => 'Periodo de referencia',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'note_label' => 'Nota',
        'created' => 'El valor objetivo se ha creado.',
        'updated' => 'El valor objetivo se ha actualizado.',
        'deleted' => 'El valor objetivo se ha eliminado.',
        'delete_confirm' => '¿Eliminar realmente este valor objetivo?',
        'none' => '–',
        'soll' => 'Objetivo',
        'ist' => 'Real',
        'deviation' => 'Desviación',
        'met' => 'alcanzado',
        'missed' => 'no alcanzado',
        'no_target' => 'Sin objetivo',
        'metric' => [
            'contributionMargin' => 'Margen de contribución (%)',
            'billableRate' => 'Tasa facturable (%)',
            'reworkShare' => 'Proporción de reproceso (%)',
            'slaComplianceRate' => 'Tasa de cumplimiento de SLA (%)',
            'utilization' => 'Utilización (%)',
        ],
        'scope' => [
            'org' => 'Organización (global)',
            'customer' => 'Cliente',
            'project' => 'Proyecto',
            'user' => 'Empleado',
        ],
        'period' => [
            'month' => 'Mes',
            'quarter' => 'Trimestre',
            'year' => 'Año',
        ],
    ],

    'cohort' => [
        'nav' => 'Comparación de cohortes',
        'title' => 'Comparación de cohortes (antes/después de la formación)',
        'subtitle' => 'Compara un indicador por empleado en el periodo antes y después de adquirir una formación.',
        'qualification' => 'Formación / cualificación',
        'metric' => [
            'billableRate' => 'Tasa facturable (%)',
            'reworkShare' => 'Proporción de reproceso (%)',
        ],
        'metric_label' => 'Indicador',
        'window' => 'Ventana de comparación (días)',
        'choose' => 'Seleccione una formación.',
        'member' => 'Empleado',
        'acquired_on' => 'Adquirida el',
        'before' => 'Antes',
        'after' => 'Después',
        'delta' => 'Δ',
        'improved' => 'Mejorado',
        'no_date' => 'sin fecha de adquisición',
        'no_date_hint' => 'Sin una fecha de adquisición registrada (cualificación "válida desde") no se puede formar una división antes/después.',
        'no_data_window' => 'Registros de tiempo insuficientes en una de las ventanas.',
        'aggregate' => 'Cohorte total (media)',
        'members_with_date' => 'con fecha de adquisición',
        'members_without_date' => 'sin fecha de adquisición',
        'improved_count' => 'mejorados',
        'data_note' => 'Fuente de la fecha de adquisición: el "válido desde" de la asignación de cualificación. Los indicadores se derivan de los mismos campos de registro de tiempo (facturable/no facturable) que la vista de rentabilidad.',
    ],
];
