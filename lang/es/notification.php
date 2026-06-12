<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : notification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'center' => 'Notificaciones',
        'center_subtitle' => 'Tus notificaciones en la aplicación — leídas y no leídas.',
        'empty' => 'Sin notificaciones.',
        'empty_message' => 'En cuanto un evento te afecte, aparecerá aquí.',
        'rules' => 'Reglas de notificación',
        'rules_subtitle' => 'Reglas por tipo de evento: canales, destinatarios y escalado.',
        'rules_help' => '¿Cómo funcionan las reglas de notificación?',
        'rules_help_text' => 'Para cada tipo de evento, la regla define si se envían notificaciones, por qué canales y quién las recibe (persona afectada, roles, personas fijas). Sin una regla guardada se aplica el valor predeterminado mostrado. Escalado: si un evento vencido sigue sin resolverse tras el tiempo configurado, se notifica además al rol de escalado.',
        'edit_rule' => 'Editar regla de notificación',
        'preferences' => 'Notificaciones',
    ],

    'field' => [
        'event' => 'Evento',
        'enabled' => 'Activo',
        'rule_enabled' => 'Notificaciones activadas para este evento',
        'channels' => 'Canales',
        'recipients' => 'Destinatarios',
        'affected_user' => 'Persona afectada',
        'notify_affected_help' => 'Notificar a la persona afectada (p. ej. asignada o solicitante)',
        'recipient_roles' => 'Roles destinatarios',
        'recipient_users' => 'Destinatarios fijos adicionales',
        'fixed_users' => 'destinatarios fijos',
        'escalation' => 'Escalado',
        'escalation_enabled' => 'Escalado activado',
        'escalation_help' => 'Si el evento sigue sin resolverse durante el tiempo configurado tras la primera notificación, se notifica además al rol de escalado.',
        'escalation_unsupported' => 'El escalado solo está disponible para eventos vencidos.',
        'escalate_after_hours' => 'Escalar después de (horas)',
        'escalation_role' => 'Rol de escalado',
        'escalation_summary' => 'tras :hours h a :role',
        'default_rule' => 'Predeterminado (aún sin personalizar)',
        'unread' => 'nuevo',
        'yes' => 'Sí',
        'no' => 'No',
        'mail_enabled' => 'Recibir notificaciones por correo electrónico',
        'quiet_from' => 'Horas de silencio desde',
        'quiet_to' => 'Horas de silencio hasta',
        'preferences_help' => 'Las notificaciones en la aplicación siempre se recopilan. El correo (y push) pueden desactivarse; durante las horas de silencio no se envían correos/push.',
    ],

    'action' => [
        'mark_read' => 'Marcar como leída',
        'mark_all_read' => 'Marcar todas como leídas',
        'open' => 'Abrir',
        'show_all' => 'Mostrar todas',
        'edit' => 'Editar',
        'save' => 'Guardar',
    ],

    'flash' => [
        'all_read' => 'Todas las notificaciones se marcaron como leídas.',
        'rule_saved' => 'La regla de notificación para «:event» se ha guardado.',
    ],

    'mail' => [
        'subject' => ':event: :title',
        'subject_escalation' => 'Escalado — :event: :title',
        'greeting' => 'Hola :name,',
        'action' => 'Abrir en el sistema',
    ],

    'message' => [
        'issue_assigned' => ':actor te ha asignado este punto abierto.',
        'due_soon' => 'Vence el :date.',
        'overdue' => 'Vencido desde el :date.',
        'followup_due_soon' => 'Acción de seguimiento vence el :date.',
        'followup_overdue' => 'Acción de seguimiento vencida desde el :date.',
        'followup_fallback_title' => 'Seguimiento de una nota de comunicación',
        'expiring_soon' => 'El documento caduca el :date.',
        'expired' => 'El documento está caducado desde el :date.',
        'correction_requested_title' => 'Solicitud de corrección horaria de :user para el :date',
        'correction_decided_title' => 'Decisión sobre tu solicitud de corrección (:date)',
        'correction_approved' => 'Tu solicitud fue aprobada. :note',
        'correction_rejected' => 'Tu solicitud fue rechazada. :note',
        'month_submitted_title' => 'Cierre mensual :period enviado por :user',
        'certificate_expiring' => 'El certificado vence el :date — planifique la recertificación a tiempo.',
        'corrective_action_overdue' => 'Acción correctiva vencida desde el :date.',
        'risk_review_due' => 'Revisión de la evaluación de riesgo aceptada pendiente para el :date.',
    ],
];
