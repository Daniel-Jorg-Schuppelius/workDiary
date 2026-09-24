<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : journal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Journal-Ereignisse (MVP-864): `journal.<modulcode>.<event>`, gelesen von
// JournalEntry::label(); Ereignisse mit Punkten liegen verschachtelt
// (`order.created` → ['order' => ['created' => …]]).
return [
    'finance' => [
        'analyzed' => 'Analizado',
        'blocked' => 'Bloqueado',
        'cancelled' => 'Cancelado',
        'completed' => 'Completado',
        'cutover_executed' => 'Cambio ejecutado',
        'item_decided' => 'Posición decidida',
        'parallel_run_started' => 'Funcionamiento en paralelo iniciado',
        'planned' => 'Planificado',
        'status_changed' => 'Estado modificado',
        'position_edited' => 'Posición editada',
        'position_removed' => 'Posición eliminada',
        'positions_merged' => 'Posiciones fusionadas',
        'texts_edited' => 'Textos editados',
        'discarded' => 'Descartado',
        'finalized' => 'Consolidado',
        'sources_removed' => 'Fuentes eliminadas',
        'return_processed' => 'Devolución procesada',
        'skonto_accepted' => 'Descuento por pronto pago aceptado',
        'unmatched' => 'Asignación anulada',
        'accounting' => [
            'opening_balance_imported' => 'Balance de apertura importado',
        ],
    ],
    'privacy' => [
        'assessed' => 'Evaluado',
        'authority_report_recorded' => 'Notificación a la autoridad registrada',
        'closed' => 'Cerrado',
        'controller_notified' => 'Responsable informado',
        'measure_added' => 'Medida añadida',
        'measure_completed' => 'Medida completada',
        'notification_decided' => 'Obligación de notificar decidida',
        'opened' => 'Abierto',
        'reported' => 'Notificado',
        'deadline_reminder' => 'Recordatorio de plazo',
        'assigned' => 'Asignado',
        'decided' => 'Decidido',
        'identity_verified' => 'Identidad verificada',
        'portal_email_confirmed' => 'Correo del portal confirmado',
        'portal_receipt_failed' => 'Acuse de recibo del portal fallido',
        'portal_receipt_sent' => 'Acuse de recibo del portal enviado',
        'portal_submitted' => 'Presentado a través del portal',
        'shredded' => 'Destruido',
        'subject_export_generated' => 'Exportación del interesado generada',
    ],
    'whistleblowing' => [
        'assigned' => 'Asignado',
        'deadline_reminder' => 'Recordatorio de plazo',
        'decided' => 'Decidido',
        'identity_verified' => 'Identidad verificada',
        'opened' => 'Abierto',
        'portal_email_confirmed' => 'Correo del portal confirmado',
        'portal_receipt_failed' => 'Acuse de recibo del portal fallido',
        'portal_receipt_sent' => 'Acuse de recibo del portal enviado',
        'portal_submitted' => 'Presentado a través del portal',
        'shredded' => 'Destruido',
        'subject_export_generated' => 'Exportación generada',
    ],
    'agile' => [
        'backlog' => [
            'added' => 'Añadido al backlog',
            'removed' => 'Eliminado del backlog',
            'reranked' => 'Backlog reordenado',
        ],
        'column' => [
            'moved' => 'Columna cambiada',
        ],
        'sprint' => [
            'item_added' => 'Añadido al sprint',
            'item_removed' => 'Eliminado del sprint',
            'started' => 'Sprint iniciado',
            'completed' => 'Sprint completado',
            'cancelled' => 'Sprint cancelado',
        ],
        'item' => [
            'blocked' => 'Bloqueado',
            'unblocked' => 'Desbloqueado',
        ],
        'points' => [
            'changed' => 'Puntos de historia modificados',
        ],
        'override' => [
            'wip' => 'Límite WIP anulado',
            'dod' => 'Definition of Done anulada',
            'criteria' => 'Criterios de aceptación anulados',
        ],
        'epic' => [
            'assigned' => 'Épica asignada',
        ],
    ],
    'diary' => [
        'order' => [
            'created' => 'Orden creada',
            'accept' => 'Orden aceptada',
            'start' => 'Orden iniciada',
            'pause' => 'Orden pausada',
            'resume' => 'Orden reanudada',
            'complete' => 'Orden finalizada',
            'acceptance' => 'Recepción iniciada',
            'invoice' => 'Factura generada',
            'cancel' => 'Orden cancelada',
        ],
        'dispatch' => [
            'gap_fill_applied' => 'Propuesta de hueco aplicada',
            'gap_fill_dismissed' => 'Propuesta de hueco descartada',
            'calendly_confirmed' => 'Solicitud de Calendly confirmada',
        ],
        'issue' => [
            'created' => 'Punto abierto creado',
            'assigned' => 'Asignado',
            'started' => 'Iniciado',
            'blocked' => 'Bloqueado',
            'unblocked' => 'Desbloqueado',
            'completed' => 'Completado',
            'wontDo' => 'No se realizará',
            'reopened' => 'Reabierto',
            'dueDateChanged' => 'Fecha límite modificada',
            'severityChanged' => 'Gravedad modificada',
            'visibilityChanged' => 'Visibilidad modificada',
            'commentAdded' => 'Comentario añadido',
            'attachmentAdded' => 'Adjunto añadido',
        ],
    ],
    'time' => [
        'month' => [
            'approved' => 'Mes aprobado',
            'locked' => 'Mes bloqueado',
            'rejected' => 'Mes rechazado',
            'submitted' => 'Mes enviado',
        ],
        'export' => [
            'delivered' => 'Entregado',
            'downloaded' => 'Descargado',
            'line_updated' => 'Línea actualizada',
            'preparing' => 'En preparación',
            'ready' => 'Listo',
            'rejected' => 'Rechazado',
            'superseded' => 'Sustituido',
            'delivered_auto' => 'Entregado automáticamente',
            'delivery_failed' => 'Entrega fallida',
        ],
    ],
    'procedure' => [
        'procedure' => [
            'runStarted' => 'Ejecución iniciada',
            'stepCompleted' => 'Paso completado',
            'stepFailed' => 'Paso fallido',
            'stepDeviated' => 'Paso con desviación',
            'stepNA' => 'Paso no aplicable',
            'stepUnlocked' => 'Paso desbloqueado',
            'stepBlocked' => 'Paso bloqueado',
            'runCompleted' => 'Ejecución completada',
            'runCompletionRejected' => 'Cierre rechazado',
            'runAborted' => 'Ejecución abortada',
            'secondPersonAssigned' => 'Segunda persona asignada',
            'secondPersonSigned' => 'Segunda persona ha firmado',
            'secondPersonRequested' => 'Segunda persona solicitada',
            'secondPersonRevoked' => 'Segunda persona retirada',
            'backupRegistered' => 'Copia de seguridad registrada',
            'backupVerified' => 'Copia de seguridad verificada',
            'backupRejected' => 'Copia de seguridad rechazada',
            'deviationRecorded' => 'Desviación registrada',
            'deviationUpdated' => 'Desviación actualizada',
            'deviationActionTriggered' => 'Medida por desviación activada',
            'criticalRiskAccepted' => 'Riesgo crítico aceptado',
        ],
    ],
    'protocol' => [
        'protocol' => [
            'created' => 'Acta creada',
            'itemAdded' => 'Posición añadida',
            'itemRemoved' => 'Posición eliminada',
            'itemReordered' => 'Posiciones reordenadas',
            'itemFilled' => 'Posición cumplimentada',
            'requestedReview' => 'Revisión solicitada',
            'returnedToDraft' => 'Devuelto a borrador',
            'signed' => 'Firmado',
            'archived' => 'Archivado',
            'supersededBy' => 'Sustituido por una versión posterior',
            'attachmentAdded' => 'Adjunto añadido',
            'attachmentRemoved' => 'Adjunto eliminado',
            'signatureRequested' => 'Firma solicitada',
            'signatureLinkOpened' => 'Enlace de firma abierto',
            'signatureRejected' => 'Firma rechazada',
            'signatureLinkRevoked' => 'Enlace de firma revocado',
            'customerQueryRaised' => 'Consulta del cliente planteada',
            'customerQueryAnswered' => 'Consulta del cliente respondida',
            'pdfRendered' => 'PDF generado',
            'pdfDownloaded' => 'PDF descargado',
            'item' => [
                'photoAdded' => 'Foto añadida',
                'photoRemoved' => 'Foto eliminada',
                'photoReordered' => 'Fotos reordenadas',
                'photoUpdatedCaption' => 'Pie de foto modificado',
            ],
        ],
    ],
    'learning' => [
        'status_changed' => 'Estado modificado',
    ],
    'auth' => [
        'auth' => [
            'lockout' => 'Cuenta bloqueada',
            '2fa_failed' => 'Segundo factor fallido',
            'password_reset_requested' => 'Restablecimiento de contraseña solicitado',
            'impossible_travel' => 'Viaje imposible detectado',
        ],
        'wb' => [
            'login_failed' => 'Canal de denuncias: inicio de sesión fallido',
        ],
        'api' => [
            'token_invalid' => 'Token de API no válido',
        ],
        'webhook' => [
            'signature_invalid' => 'Firma de webhook no válida',
        ],
        'sso' => [
            'failed' => 'SSO fallido',
        ],
        'terminal' => [
            'badge_unknown' => 'Tarjeta de terminal desconocida',
        ],
        'admin' => [
            'ip_blocked' => 'IP de administrador de plataforma bloqueada',
        ],
    ],
];
