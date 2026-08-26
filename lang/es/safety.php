<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : safety.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Eventos de seguridad',
    ],
    'subtitle' => [
        'index' => 'Registrar y dar seguimiento a accidentes, cuasiaccidentes, peligros y defectos.',
    ],
    'empty' => 'Aún no hay eventos de seguridad registrados.',

    'field' => [
        'event_no' => 'N.º',
        'kind' => 'Tipo',
        'severity' => 'Gravedad',
        'status' => 'Estado',
        'occurred_at' => 'Ocurrido el',
        'location' => 'Lugar',
        'affected_person' => 'Persona afectada',
        'reporter' => 'Notificado por',
        'subject' => 'Vinculado a',
        'description' => 'Descripción',
        'immediate_action' => 'Acción inmediata',
        'root_cause' => 'Análisis de causa raíz',
        'closed_at' => 'Cerrado el',
        'closed_by' => 'Cerrado por',
        'followup_title' => 'Título de la medida de seguimiento',
        'followup_description' => 'Descripción (opcional)',
    ],

    'section' => [
        'status' => 'Cambiar estado',
        'followup' => 'Medida de seguimiento',
        'attachments' => 'Adjuntos',
        'followups' => 'Medidas de seguimiento',
    ],

    'no_attachments' => 'Sin adjuntos.',
    'no_followups' => 'Aún no hay medidas de seguimiento.',

    'action' => [
        'create' => 'Notificar evento',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'show' => 'Ver',
        'back' => 'Volver',
        'create_followup' => 'Crear seguimiento',
    ],

    'transition' => [
        'investigating' => 'Iniciar investigación',
        'measuresDefined' => 'Medidas definidas',
        'closed' => 'Cerrar',
    ],

    'hint' => [
        'root_cause_for_close' => 'Para cerrar el evento se requiere un análisis de causa raíz.',
        'followup' => 'Crea un punto abierto como retrabajo vinculado a este evento.',
    ],

    'flash' => [
        'created' => 'Evento de seguridad registrado.',
        'updated' => 'Evento de seguridad actualizado.',
        'deleted' => 'Evento de seguridad eliminado.',
        'followup_created' => 'Medida de seguimiento creada.',
        'status' => [
            'reported' => 'Evento restablecido.',
            'investigating' => 'Investigación iniciada.',
            'measuresDefined' => 'Medidas definidas.',
            'closed' => 'Evento cerrado.',
        ],
    ],

    'error' => [
        'invalid_transition' => 'Cambio de estado no válido: :from → :to.',
        'close_requires_root_cause' => 'El cierre requiere un análisis de causa raíz.',
    ],

    'report' => [
        'title' => 'Análisis de seguridad',
        'nav' => 'Seguridad laboral',
        'subtitle' => 'Eventos de seguridad por tipo y gravedad en el periodo.',
        'by_kind' => 'Por tipo',
        'by_severity' => 'Por gravedad',
        'kpi' => [
            'total' => 'Eventos totales',
            'open' => 'Abiertos',
            'closed' => 'Cerrados',
            'critical' => 'Críticos',
        ],
    ],

    // Registro de seguridad laboral (Feature 132): evaluación de riesgos, formación, reconocimiento médico.
    'register' => [
        'section' => 'Seguridad laboral',
        'nav' => [
            'assessments' => 'Evaluaciones de riesgos',
            'instructions' => 'Formaciones',
            'checkups' => 'Reconocimientos médicos',
        ],
        'title' => [
            'assessments' => 'Evaluaciones de riesgos',
            'instructions' => 'Formaciones de seguridad',
            'checkups' => 'Reconocimientos médicos laborales',
        ],
        'subtitle' => [
            'assessments' => 'Evaluaciones de riesgos según § 5 ArbSchG — versionadas, con fecha de revisión.',
            'instructions' => 'Formaciones según DGUV Vorschrift 1 § 4 con justificante de participación por persona.',
            'checkups' => 'Reconocimientos médicos según ArbMedVV — solo tipo, fecha y certificado, sin datos de salud.',
        ],
        'field' => [
            'assessment_no' => 'Número',
            'version' => 'Versión',
            'area' => 'Área',
            'activity' => 'Actividad',
            'description' => 'Descripción',
            'status' => 'Estado',
            'review_due_on' => 'Revisión prevista',
            'approved_by' => 'Aprobado por',
            'approved_at' => 'Aprobado el',
            'created_by' => 'Creado por',
            'supersedes' => 'Sustituye a',
            'superseded_by' => 'Sustituido por',
            'items' => 'Peligros',
            'position' => 'Pos.',
            'hazard' => 'Peligro',
            'measure' => 'Medida',
            'severity' => 'Gravedad (G)',
            'likelihood' => 'Probabilidad (P)',
            'risk_before' => 'Riesgo antes',
            'risk_after' => 'Riesgo después',
            'before' => 'Antes de la medida',
            'after' => 'Después de la medida',
            'instruction_no' => 'Número',
            'topic' => 'Tema',
            'held_on' => 'Fecha',
            'instructor' => 'Formador/a',
            'assessment' => 'Evaluación de riesgos',
            'repeat_interval_months' => 'Repetición (meses)',
            'notes' => 'Notas',
            'participants' => 'Participantes',
            'signed' => 'Confirmado',
            'signed_at' => 'Confirmado el',
            'method' => 'Forma de justificante',
            'next_due_on' => 'Próximo vencimiento',
            'user' => 'Persona',
            'kind' => 'Tipo',
            'occasion' => 'Motivo',
            'performed_on' => 'Realizado el',
            'certificate_on_file' => 'Certificado disponible',
        ],
        'action' => [
            'create_assessment' => 'Crear evaluación de riesgos',
            'edit' => 'Editar',
            'save' => 'Guardar',
            'delete' => 'Eliminar',
            'show' => 'Ver',
            'back' => 'Volver',
            'transition' => 'Cambiar estado',
            'new_version' => 'Crear versión siguiente',
            'add_item' => 'Añadir peligro',
            'edit_item' => 'Editar peligro',
            'create_instruction' => 'Registrar formación',
            'sign' => 'Confirmar participación',
            'create_checkup' => 'Registrar reconocimiento',
        ],
        'filter' => [
            'all' => 'Todos',
            'current_only' => 'Solo versiones actuales',
            'open_only' => 'Solo con confirmaciones pendientes',
            'due_only' => 'Solo vencidos',
        ],
        'kpi' => [
            'review_due' => 'Revisión vencida',
            'instruction_due' => 'Repetición vencida',
            'checkup_due' => 'Reconocimiento vencido',
        ],
        'empty' => [
            'assessments' => 'Aún no hay evaluaciones de riesgos.',
            'items' => 'Aún no hay peligros registrados.',
            'instructions' => 'Aún no hay formaciones registradas.',
            'participants' => 'Sin participantes.',
            'checkups' => 'Aún no hay reconocimientos registrados.',
        ],
        'hint' => [
            'frozen' => 'Esta versión está aprobada y congelada. Los cambios se realizan mediante una versión siguiente.',
            'approve_requires_items' => 'La aprobación requiere al menos un peligro.',
            'sign_self' => 'Confirme su participación — se registran nombre, hora y dirección IP como justificante.',
            'no_health_data' => 'No se almacenan resultados ni diagnósticos — solo tipo, fecha y si el certificado está disponible.',
            'after_optional' => 'Riesgo después de la medida opcional — indicar ambos valores juntos.',
            'pdf_not_in_mvp' => 'El justificante en PDF llegará en una fase posterior.',
        ],
        'confirm' => [
            'delete_assessment' => '¿Eliminar la evaluación de riesgos?',
            'delete_item' => '¿Eliminar el peligro?',
            'delete_instruction' => '¿Eliminar la formación?',
            'delete_checkup' => '¿Eliminar la entrada del reconocimiento?',
            'sign' => '¿Confirmar ahora la participación (vinculante)?',
        ],
        'flash' => [
            'assessment_created' => 'Evaluación de riesgos creada.',
            'assessment_updated' => 'Evaluación de riesgos actualizada.',
            'assessment_transitioned' => 'Estado cambiado.',
            'assessment_version_created' => 'Versión siguiente :version creada.',
            'assessment_deleted' => 'Evaluación de riesgos eliminada.',
            'item_created' => 'Peligro añadido.',
            'item_updated' => 'Peligro actualizado.',
            'item_deleted' => 'Peligro eliminado.',
            'instruction_created' => 'Formación registrada.',
            'instruction_updated' => 'Formación actualizada.',
            'instruction_deleted' => 'Formación eliminada.',
            'participation_signed' => 'Participación confirmada.',
            'checkup_created' => 'Reconocimiento registrado.',
            'checkup_updated' => 'Reconocimiento actualizado.',
            'checkup_deleted' => 'Entrada del reconocimiento eliminada.',
        ],
        'error' => [
            'assessment_frozen' => 'Las evaluaciones aprobadas están congeladas — cree una versión siguiente.',
            'approve_requires_items' => 'La aprobación requiere al menos un peligro.',
            'new_version_requires_approved' => 'Una versión siguiente solo es posible desde una versión aprobada.',
            'after_pair_incomplete' => 'Riesgo después de la medida: indicar gravedad y probabilidad juntas.',
            'sign_only_self' => 'Solo la persona registrada puede confirmar su participación.',
            'already_signed' => 'La participación ya está confirmada.',
            'delete_with_signatures' => 'Las formaciones con justificantes confirmados no se pueden eliminar.',
        ],
        'status_summary' => ':signed de :total confirmados',
    ],
];
