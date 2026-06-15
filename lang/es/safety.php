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
];
