<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : day-close.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Cierre diario (MVP-015, docs/tagesabschluss.md) — textos de la página,
 * mensajes del validador (§4), mensajes flash y errores. Mantenido en
 * paridad de/en/fr/it/es; las etiquetas de enums están en enums.php
 * (dayClosure.status / dayCorrection.status).
 */

return [
    'title' => 'Cierre diario',
    'title_day' => 'Cierre diario :day',

    'subtitle' => [
        'own' => 'Revisar el día, completar lagunas y cerrarlo como totalmente registrado.',
        'other' => 'Cierre diario de :name.',
    ],

    'section' => [
        'attendance' => 'Presencia',
        'breaks' => 'Pausas',
        'entries' => 'Tiempos de pedido y proyecto',
        'issues' => 'Lagunas y avisos',
        'balance' => 'Balance',
        'corrections' => 'Solicitudes de corrección',
    ],

    'field' => [
        'date' => 'Fecha',
        'recorded_break' => 'Pausa registrada',
        'required_break' => 'Pausa obligatoria',
        'target' => 'Horas previstas',
        'gross' => 'Presencia (bruta)',
        'break' => 'Pausa',
        'net' => 'Trabajo neto',
        'booked' => 'Registrado',
        'diff' => 'Diferencia',
        'day_balance' => 'Saldo del día',
        'month_balance' => 'Saldo del mes en curso',
        'duration' => 'Duración',
        'project' => 'Pedido / proyecto',
        'activity' => 'Actividad',
        'comment' => 'Comentario',
        'billable' => 'Facturable',
        'reason' => 'Justificación',
        'reason_placeholder' => 'Justificación (mínimo :min caracteres)',
        'decision' => 'Decisión',
    ],

    'action' => [
        'prev_day' => 'Día anterior',
        'next_day' => 'Día siguiente',
        'today' => 'Hoy',
        'pick_date' => 'Elegir fecha',
        'show_day' => 'Mostrar día',
        'clock_in' => 'Fichar ahora',
        'clock_out' => 'Fichar salida ahora',
        'book_time' => 'Registrar tiempo',
        'save' => 'Guardar',
        'close_day' => 'Cerrar día',
        'request_correction' => 'Solicitar corrección',
        'reopen' => 'Reabrir día',
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'cancel' => 'Cancelar',
    ],

    'status' => [
        'attendance_open' => 'abierto',
        'comment_missing' => 'falta',
        'billable' => 'facturable',
    ],

    'hint' => [
        'no_attendance' => 'Sin fichajes en este día.',
        'attendance_correction_only' => 'Los fichajes solo pueden modificarse mediante una solicitud de corrección.',
        'attendance_locked' => 'Los fichajes de presencia quedan bloqueados tras aprobarse una corrección — hasta el nuevo cierre solo pueden modificarse los registros.',
        'no_entries' => 'Aún no hay registros en este día.',
        'break_recorded' => 'Pausa: :min min',
        'no_issues' => 'Sin incidencias — el día está registrado de forma coherente.',
        'month_locked' => 'Este día pertenece a un mes aprobado y está bloqueado — el cierre y las solicitudes de corrección pasan por la aprobación mensual.',
        'correction_intro' => 'Describa qué debe corregirse en este día.',
        'reopen_intro' => 'El día se reabre sin solicitud de corrección — la justificación se guarda en el registro de auditoría.',
    ],

    // Las 7 comprobaciones de coherencia del §4 — clave = código de la
    // comprobación (DayClosureValidator), los puntos del código se anidan.
    'check' => [
        'attendance' => [
            'missing_close' => 'El reloj de fichaje sigue abierto — por favor, fiche la salida.',
        ],
        'time' => [
            'unallocated_minutes' => ':minutes minutos de presencia aún no están asignados a ningún registro.',
            'gap_in_attendance' => 'Laguna de presencia de :minutes minutos sin marcador de pausa.',
        ],
        'break' => [
            'required' => 'Pausa obligatoria no cumplida: :taken de :required minutos registrados.',
        ],
        'balance' => [
            'threshold' => 'El saldo del día de :hours horas supera ±2 h.',
        ],
        'entry' => [
            'missing_comment' => ':count registro(s) facturable(s) sin comentario.',
        ],
        'worktime' => [
            'overrun' => 'Tiempo de trabajo neto superior a 10 horas (:minutes minutos, ArbZG).',
        ],
        'unknown' => 'Comprobación desconocida: :code',
    ],

    'flash' => [
        'saved' => 'El día :day se ha guardado.',
        'closed' => 'El día :day se ha cerrado.',
        'correction_requested' => 'Se ha solicitado la corrección para :day.',
        'correction_approved' => 'Se ha aprobado la corrección para :day.',
        'correction_rejected' => 'Se ha rechazado la corrección para :day.',
        'reopened' => 'El día :day se ha reabierto.',
    ],

    'errors' => [
        'month_entry_locked' => 'El mes está aprobado: para tiempos posteriores presente una solicitud de corrección.',
        'future_day' => 'Un día futuro no puede cerrarse.',
        'blocking_warnings' => 'El día tiene avisos bloqueantes y no puede cerrarse.',
        'illegal_day_status' => 'Acción no permitida: el estado del día es :status.',
        'illegal_request_status' => 'Acción no permitida: el estado de la solicitud es :status.',
        'reason_too_short' => 'Se requiere una justificación de al menos :n caracteres.',
        'month_locked' => 'El mes ya está aprobado o bloqueado — reabra primero la aprobación mensual.',
        'owner_missing' => 'Cierre diario sin propietario válido.',
        'closure_missing' => 'Solicitud de corrección sin cierre diario asociado.',
        // Justificaciones del tooltip para el botón de cierre desactivado (§2.6).
        'close_blocked' => [
            'future' => 'Un día futuro no puede cerrarse.',
            'month_locked' => 'El mes ya está aprobado o bloqueado.',
            'blocking' => 'Los avisos bloqueantes (⛔) deben resolverse primero.',
            'not_open' => 'El día no está abierto.',
        ],
    ],

    'modal' => [
        'correction_title' => 'Solicitar corrección',
        'reopen_title' => 'Reabrir día',
    ],
];
