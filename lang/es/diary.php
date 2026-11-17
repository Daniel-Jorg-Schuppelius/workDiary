<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Baja',
        'normal' => 'Normal',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ],
    'location_mode' => [
        'onsite' => 'En el sitio',
        'remote' => 'En remoto',
        'hybrid' => 'Híbrido',
    ],
    'mode' => [
        'fixed' => 'Planificado',
        'deadline' => 'Fecha límite',
        'window' => 'Ventana',
        'recurring' => 'Recurrente',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Planificado',
        'Accepted' => 'Aceptado',
        'InProgress' => 'En curso',
        'WaitingCustomer' => 'Esperando respuesta',
        'WaitingMaterial' => 'Esperando material',
        'Completed' => 'Completado',
        'AcceptedFinal' => 'Recepcionado',
        'Invoiced' => 'Facturado',
        'Cancelled' => 'Cancelado',
        'InReview' => 'En revisión',
        'ReviewQueried' => 'Consulta',
        'ReviewApproved' => 'Aprobado',
    ],
    'planned_duration' => [
        'label' => 'Duración prevista (HH:MM)',
        'hint' => 'Vacío: duración de la franja horaria o de la cita. Cuenta como plan en Plan/real, Análisis de tipos de orden y Capacidad de personal.',
        'format' => 'Indique la duración prevista en horas:minutos, p. ej. 1:30.',
        'range' => 'La duración prevista debe estar entre 0:01 y 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Previsto/real',
        'no_plan' => 'sin previsión',
        'own_only' => 'solo tiempos propios',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Devolver la aceptación',
            'revert_invoiced' => 'Revertir la facturación',
            'submit_review' => 'Enviar a revisión',
            'withdraw_review' => 'Retirar el envío',
            'approve_review' => 'Aprobar',
            'query_review' => 'Plantear una consulta',
            'resubmit_review' => 'Volver a enviar',
            'approve_and_complete' => 'Aprobar y cerrar',
        ],
        'reject' => [
            'title' => 'Devolver la aceptación',
            'legend' => 'Devolución',
            'hint' => 'La orden vuelve a «Planificado» y otra persona puede aceptarla. El motivo queda en el historial.',
        ],
        'handover' => [
            'legend' => 'Recepción',
            'accepted_by' => 'Recibido por (nombre en el cliente)',
            'hint' => 'El protocolo no lleva firma del cliente. Indique la persona que recibió el trabajo en el cliente.',
        ],
        'accepted_final_by' => 'Recibido por :name el :date',
        'invoiced_with' => 'Facturado con :reference',
        'invoiced' => [
            'transfer_reference' => 'Traspaso n.º :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Orden cancelada: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'La orden solo puede cerrarse cuando no haya ningún cronómetro en marcha. Aún activo: :names.',
        ],
    ],
    // Periodenaufträge mit interner Prüfung (Feature 198), Folgeperiode und Demo.
    'period' => [
        'label' => 'Periodo',
        'badge' => 'Periodo: :period',
        'placeholder' => '2026, 2026-H1, 2026-Q1, 2026-03',
        'hint' => 'Año (2026), semestre (2026-H1), trimestre (2026-Q1) o mes (2026-03).',
        'invalid' => 'Indique el periodo como año (2026), semestre (2026-H1), trimestre (2026-Q1) o mes (2026-03).',
        'kind_mismatch' => 'Este tipo de entrada exige un periodo del tipo: :kinds.',
    ],
    'product_group' => [
        'label' => 'Grupo de productos',
        'invalid' => 'Este grupo de productos no existe en su organización.',
    ],
    'review' => [
        'required_badge' => 'Revisión obligatoria',
        'reviewer_named' => 'Revisión por :name',
        'submitted_by' => 'Enviado por :name el :date',
        'reviewer_label' => 'Persona revisora',
        'reviewer_any' => 'Cualquier persona con derecho de revisión',
        'reviewer_hint' => 'Sin selección, revisa cualquier persona con el derecho «Revisar órdenes», nunca quien envía.',
        'waive_action' => 'Suprimir la revisión',
        'require_action' => 'Exigir revisión',
        'waive_hint' => 'Sin revisión obligatoria, la orden se cierra directamente. El cambio queda con su motivo en el historial y en el registro de auditoría.',
        'require_hint' => 'Con revisión obligatoria, la orden se envía a revisión antes del cierre y la aprueba una segunda persona.',
        'approve_complete_hint' => 'Aquí la aprobación es también el cierre: aprueba y cierra la orden en un solo paso.',
        'query' => [
            'title' => 'Consulta sobre la revisión',
            'hint' => 'La orden vuelve con su pregunta a quien la trabaja y después se envía de nuevo. La pregunta queda en el historial.',
            'legend' => 'Consulta',
            'question' => 'Su pregunta',
        ],
        'error' => [
            'own_submission' => 'Quien envió una orden a revisión no puede aprobarla ni plantear una consulta.',
            'requirement_locked' => 'La revisión obligatoria ya no se puede cambiar en el estado «:status».',
            'question_required' => 'Una consulta necesita un texto.',
            'not_in_review' => 'La orden ya no está en revisión.',
        ],
        'flash' => [
            'waived' => 'Revisión obligatoria suprimida para esta orden.',
            'required' => 'Revisión obligatoria activada para esta orden.',
        ],
        'inbox_reference' => 'Enviado por :name el :date',
        'inbox_type' => 'Revisión de orden',
        'queue' => [
            'title' => 'Para revisar',
            'subtitle' => 'Órdenes que puede revisar, por vencimiento y luego por fecha de envío. Sus propios envíos no aparecen aquí.',
            'empty_title' => 'Nada que revisar',
            'empty_message' => 'Ahora mismo no hay ninguna orden pendiente de su revisión.',
            'column' => [
                'order' => 'Orden',
                'submitted' => 'Enviado',
                'actions' => 'Acciones',
            ],
        ],
        'widget' => [
            'title' => 'Para revisar',
            'description' => 'Órdenes pendientes de su revisión interna, por vencimiento y luego por fecha de envío.',
            'open_queue' => 'Todas →',
        ],
    ],
    'follow_up' => [
        'action' => 'Crear el periodo siguiente',
        'title' => 'Crear el periodo siguiente :period',
        'submit' => 'Crear',
        'copied' => 'Se copian cliente, tipo de entrada, grupo de productos, responsable, revisión obligatoria y persona revisora; los procedimientos empiezan como nuevas ejecuciones. Las notas abiertas aparecen en la nueva orden.',
        'not_copied' => 'No se copian el contenido y las notas, los tiempos ni el resultado.',
        'exists_hint' => 'Para :period ya existe esta orden:',
        'content' => 'Periodo siguiente :period de la orden #:id.',
        'error' => [
            'no_series' => 'Solo hay periodo siguiente para órdenes con periodo, cliente y tipo de entrada.',
            'exists' => 'Ya existe la orden para este periodo (#:id).',
            'selection' => 'Elija un tipo de entrada y un periodo.',
        ],
        'flash' => [
            'created' => 'Orden para :period creada.',
            'bulk' => ':created órdenes para :period creadas, :skipped omitidas.',
        ],
        'bulk' => [
            'title' => 'Periodo siguiente en bloque',
            'subtitle' => 'Crear la orden del periodo siguiente para todas las órdenes de un tipo de entrada y periodo: primero la vista previa, luego la creación.',
            'choose_title' => 'Elegir tipo de entrada y periodo',
            'choose_message' => 'La vista previa muestra qué órdenes se crearán y cuáles ya existen.',
            'none_title' => 'Sin órdenes',
            'none_message' => 'No hay órdenes de este tipo de entrada en :period.',
            'summary' => ':count órdenes en :from, :creatable de ellas nuevas para :to.',
            'limit' => 'Se procesan como máximo :limit órdenes a la vez.',
            'submit' => 'Crear :count órdenes',
            'column' => [
                'source' => 'Orden',
                'target' => 'Nuevo periodo',
                'result' => 'Resultado',
            ],
            'result' => [
                'create' => 'se creará',
                'exists' => 'ya existe',
                'no_customer' => 'sin cliente',
                'duplicate_source' => 'duplicada en la vista previa',
            ],
        ],
    ],
    'demo' => [
        'entry_type' => 'Orden anual (demo)',
        'title' => 'Orden anual :year',
        'content' => 'Orden anual de demostración: reunir documentos, tramitarlos y enviarlos a revisión.',
        'approval_note' => 'Revisado, sin observaciones.',
        'summary' => 'Documentos completos, resultado enviado.',
        'carry_forward' => 'El año que viene, solicitar los justificantes antes de finales de febrero.',
    ],
    'entry_type' => [
        'legend' => 'Periodo y revisión',
        'description' => 'Órdenes periódicas (año, semestre, trimestre, mes) y revisión interna antes del cierre.',
        'requires_period' => 'Periodo obligatorio al crear',
        'requires_period_hint' => 'Sin selección, el periodo es opcional. Con varios tipos vale cualquiera de ellos.',
        'requires_review' => 'Revisión obligatoria antes del cierre (principio de los cuatro ojos)',
    ],
];
