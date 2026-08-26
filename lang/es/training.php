<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : training.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'section' => 'Formación',
    'nav' => [
        'courses' => 'Catálogo de cursos',
        'requirements' => 'Matriz de obligaciones',
        'assignments' => 'Plan de formación',
    ],
    'title' => [
        'courses' => 'Catálogo de cursos',
        'requirements' => 'Matriz de obligaciones',
        'assignments' => 'Plan de formación',
    ],
    'subtitle' => [
        'courses' => 'Cursos con proveedor, duración, validez y base legal — los justificantes quedan en el registro de seguridad.',
        'requirements' => 'Qué rol o área de actividad debe qué curso; de ahí surge el plan por persona.',
        'assignments' => 'Quién debe qué formación y para cuándo — con el justificante de la instrucción.',
    ],

    'field' => [
        'code' => 'Código del curso',
        'title' => 'Título',
        'provider_kind' => 'Proveedor',
        'provider_name' => 'Nombre del proveedor',
        'duration_minutes' => 'Duración (minutos)',
        'validity_months' => 'Validez (meses)',
        'is_mandatory' => 'Formación obligatoria',
        'legal_basis' => 'Base legal',
        'cost' => 'Coste',
        'cost_amount' => 'Coste (informativo)',
        'cost_currency' => 'Moneda',
        'lead_days' => 'Antelación (días)',
        'notes' => 'Notas',
        'is_active' => 'Activo',
        'course' => 'Curso',
        'version' => 'Versión del curso',
        'versions' => 'Versiones del curso',
        'version_label' => 'Etiqueta de versión',
        'valid_from' => 'Válido desde',
        'content_summary' => 'Resumen del contenido',
        'subject' => 'Grupo destinatario',
        'subject_kind' => 'Tipo de grupo destinatario',
        'subject_role' => 'Rol',
        'subject_team' => 'Área de actividad (equipo)',
        'first_due_days' => 'Primer vencimiento (días)',
        'user' => 'Persona',
        'due_at' => 'Vence el',
        'fulfilled_at' => 'Acreditado el',
        'proof' => 'Justificante',
        'state' => 'Estado',
        'source' => 'Origen',
        'requirements_count' => 'Asignaciones',
        'assignments_count' => 'Entradas del plan',
    ],

    'action' => [
        'create_course' => 'Crear curso',
        'create_requirement' => 'Crear asignación',
        'create_assignment' => 'Crear entrada del plan',
        'create_version' => 'Crear versión',
        'sync_assignments' => 'Actualizar plan',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'show' => 'Ver',
        'back' => 'Volver',
    ],

    'filter' => [
        'all' => 'Todos',
        'mandatory_only' => 'Solo obligatorios',
        'state' => 'Estado',
        'subject_kind' => 'Grupo destinatario',
    ],

    'kpi' => [
        'mandatory' => 'Cursos obligatorios',
        'active_requirements' => 'Asignaciones activas',
        'overdue' => 'Vencidos',
    ],

    'empty' => [
        'courses' => 'Todavía no hay ningún curso en el catálogo.',
        'versions' => 'Todavía no hay ninguna versión del curso.',
        'requirements' => 'Todavía no hay ninguna obligación asignada.',
        'assignments' => 'Todavía no hay entradas en el plan de formación.',
    ],

    'hint' => [
        'cost_informational' => 'Los costes son solo informativos — no generan asiento ni documento.',
        'instruction_course' => 'Con referencia al curso, esta participación cuenta como justificante del plan de formación.',
        'no_second_guard' => 'El plan de formación avisa y evalúa; el bloqueo sigue en el estado de cualificación.',
        'proof_in_register' => 'Los justificantes se registran exclusivamente como instrucción en el registro de seguridad.',
        'sync' => 'La actualización crea las entradas que faltan y elimina las que ya no se exigen y no tienen justificante.',
    ],

    'confirm' => [
        'delete_course' => '¿Eliminar el curso?',
        'delete_version' => '¿Eliminar la versión del curso?',
        'delete_requirement' => '¿Eliminar la asignación?',
        'delete_assignment' => '¿Eliminar la entrada del plan?',
    ],

    'flash' => [
        'course_created' => 'El curso se ha creado.',
        'course_updated' => 'El curso se ha actualizado.',
        'course_deleted' => 'El curso se ha eliminado.',
        'version_created' => 'La versión se ha creado.',
        'version_deleted' => 'La versión se ha eliminado.',
        'requirement_created' => 'La asignación se ha creado.',
        'requirement_updated' => 'La asignación se ha actualizado.',
        'requirement_deleted' => 'La asignación se ha eliminado.',
        'assignment_created' => 'La entrada del plan se ha creado.',
        'assignment_deleted' => 'La entrada del plan se ha eliminado.',
        'assignments_synced' => 'Plan actualizado: :created añadidas, :removed eliminadas.',
    ],

    'error' => [
        'delete_with_proof' => 'Este curso tiene justificantes — solo puede desactivarse.',
        'delete_last_version' => 'La última versión del curso no se puede eliminar.',
        'delete_version_in_use' => 'Esta versión está acreditada en una instrucción y se mantiene.',
    ],

    'report' => [
        'title' => 'Análisis de formación',
        'nav' => 'Formación',
        'subtitle' => 'Grado de cumplimiento por equipo, rol y curso en la fecha de referencia — base de la prueba de competencia.',
        'total' => 'Total',
        'team' => 'Equipo',
        'role' => 'Rol',
        'course' => 'Curso',
        'no_team' => 'Sin equipo',
        'no_role' => 'Sin rol',
        'rate' => 'Grado de cumplimiento',
        'rate_by_team' => 'Grado de cumplimiento por equipo',
        'rate_by_course' => 'Grado de cumplimiento por curso',
        'by_team' => 'Por equipo',
        'by_role' => 'Por rol',
        'by_course' => 'Por curso',
        'kpi' => [
            'assignments' => 'Entradas del plan',
            'fulfilled' => 'Cumplidas',
            'due' => 'Pendientes',
            'overdue' => 'Vencidas',
            'rate' => 'Grado de cumplimiento',
        ],
        'empty' => 'No hay entradas del plan para el filtro seleccionado.',
    ],
];
