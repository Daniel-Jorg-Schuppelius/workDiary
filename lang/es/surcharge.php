<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => 'Reglas de recargo',
        'rules_subtitle' => 'Recargos nocturnos, de fin de semana y festivos por organización: franja horaria, porcentaje y concepto salarial para el traspaso de nómina.',
        'rules_help' => '¿Cómo funcionan las reglas de recargo?',
        'rules_help_text' => 'Cada regla describe los periodos con derecho a recargo (franja nocturna, sábado, domingo, festivo o franja personalizada) con porcentaje y concepto salarial. Durante la exportación de tiempos, las presencias se dividen en consecuencia y se reflejan como líneas de exportación adicionales por día. Si varias reglas se solapan, gana el porcentaje más alto — los recargos no se suman.',
        'create_rule' => 'Crear regla de recargo',
        'edit_rule' => 'Editar regla de recargo',
        'empty' => 'No hay reglas de recargo',
        'export_summary' => 'Recargos por empleado/a y concepto salarial',
    ],

    'field' => [
        'basics' => 'Datos básicos',
        'code' => 'Código',
        'code_help' => 'Clave corta y única (minúsculas, dígitos, ._-), p. ej. «night».',
        'label' => 'Denominación',
        'label_placeholder' => 'p. ej. Recargo nocturno',
        'kind' => 'Tipo',
        'kind_help' => 'Noche/Personalizado usan la franja horaria; sábado, domingo y festivo aplican al día completo.',
        'window' => 'Franja horaria',
        'window_help' => 'Solo para Noche/Personalizado. Las franjas que cruzan la medianoche (p. ej. 23:00–06:00) están permitidas y se dividen correctamente.',
        'window_start' => 'Franja desde',
        'window_end' => 'Franja hasta',
        'whole_day' => 'día completo',
        'percentage' => 'Recargo (%)',
        'payroll' => 'Traspaso de nómina',
        'wage_type_code' => 'Concepto salarial',
        'wage_type_code_help' => 'Número de concepto para DATEV/Lexware (p. ej. 2010). Vacío = exportar sin concepto.',
        'tax_free_limit_pct' => 'Exento hasta (%)',
        'tax_free_limit_pct_help' => "Límites § 3b EStG configurables (p. ej. noche 25/40, domingo 50, festivo 125/150). Vacío = sin división. Por encima, el resto se exporta como parte imponible con su propio tipo de salario.",
        'taxable_wage_type_code' => 'Tipo de salario parte imponible',
        'taxable_wage_type_code_help' => "Obligatorio cuando el límite exento queda por debajo del recargo. El tope del salario base en € sigue siendo cosa de la nómina externa.",
        'priority' => 'Prioridad',
        'priority_help' => 'Desempate con porcentaje igual: gana la prioridad más alta.',
        'validity' => 'Validez',
        'conditions' => 'Condiciones',
        'condition_teams' => 'Equipos',
        'condition_sites' => 'Ubicaciones',
        'condition_shift_types' => 'Tipos de turno',
        'conditions_help' => 'Vacío = se aplica a todos. Varias condiciones se combinan con Y; dentro de una lista basta una coincidencia. La ubicación se detecta mediante fichajes de terminal — sin contexto determinable, una regla condicionada no se aplica.',
        'valid_from' => 'Válida desde',
        'valid_until' => 'Válida hasta',
        'unlimited' => 'ilimitada',
        'active' => 'Activa',
        'rule_active' => 'La regla está activa',
        'hours' => 'Horas',
        'yes' => 'Sí',
        'no' => 'No',
    ],

    'action' => [
        'create' => 'Crear',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'delete_confirm' => '¿Eliminar realmente esta regla de recargo? Las exportaciones existentes no cambian.',
    ],

    'flash' => [
        'created' => 'Regla de recargo creada.',
        'updated' => 'Regla de recargo actualizada.',
        'deleted' => 'Regla de recargo eliminada.',
    ],

    'validation' => [
        'taxable_wage_type_required' => "La parte imponible necesita su propio tipo de salario.",
    ],
];
