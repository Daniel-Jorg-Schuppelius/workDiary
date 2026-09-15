<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : communication.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Comunicación',
        'followups' => 'Acciones de seguimiento abiertas',
        'notes' => 'Notas',
        'note' => 'Nota',
    ],

    'field' => [
        'type' => 'Tipo',
        'direction' => 'Dirección',
        'occurred_at' => 'Fecha y hora',
        'subject' => 'Asunto',
        'body' => 'Contenido / desarrollo',
        'result' => 'Resultado / acuerdo',
        'next_action' => 'Acción de seguimiento',
        'next_action_due_at' => 'Fecha límite',
        'next_action_user' => 'Responsable',
        'visibility' => 'Visibilidad',
        'confidential' => 'Confidencial',
        'customer_visible' => 'Visible para el cliente',
        'participants' => 'Participantes',
        'participant_name' => 'Nombre',
        'participant_role' => 'Rol',
        'participant_party' => 'Parte',
        'creator' => 'Registrado por',
        'storage' => 'Ubicación',
        'customer' => 'Cliente',
        'actions' => 'Acciones',
        'direction_choose' => 'Seleccione',
    ],

    'action' => [
        'create' => 'Registrar nota',
        'edit' => 'Editar',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'publish' => 'Publicar para el cliente',
        'mark_confidential' => 'Marcar como confidencial',
        'unmark_confidential' => 'Quitar confidencialidad',
        'complete_followup' => 'Seguimiento completado',
        'add_participant' => 'Añadir participante',
        'remove_participant' => 'Quitar participante',
        'show' => 'Ver',
    ],

    'flash' => [
        'created' => 'La nota de comunicación se ha registrado.',
        'updated' => 'La nota de comunicación se ha actualizado.',
        'deleted' => 'La nota de comunicación se ha eliminado.',
        'published' => 'La nota se ha publicado para el cliente.',
        'confidential_set' => 'La nota se ha marcado como confidencial.',
        'confidential_unset' => 'Se ha quitado la confidencialidad.',
        'followup_completed' => 'La acción de seguimiento se ha marcado como completada.',
    ],

    'error' => [
        'internal_type_requires_internal_direction' => 'Las consultas internas deben usar la dirección «Interna».',
        'internal_direction_requires_internal_visibility' => 'La comunicación interna no puede ser visible para los clientes.',
        'confidential_requires_internal_visibility' => 'Las notas confidenciales deben permanecer internas.',
        'private_not_publishable' => 'Las notas privadas quedan con quien las escribió y no pueden publicarse.',
        'occurred_at_in_future' => 'La fecha no puede estar en el futuro.',
        'due_before_occurrence' => 'La fecha límite del seguimiento debe ser posterior a la fecha de la comunicación.',
        'unknown_type' => 'Tipo de comunicación desconocido.',
        'unknown_direction' => 'Dirección desconocida.',
        'confidential_not_publishable' => 'Las notas confidenciales no se pueden publicar para los clientes.',
        'internal_not_publishable' => 'La comunicación interna no se puede publicar para los clientes.',
        'no_followup' => 'Esta nota no tiene acción de seguimiento.',
        'organization_note_not_publishable' => 'Las notas internas de la organización no se pueden compartir con clientes.',
        'call_requires_external_direction' => 'En una llamada, indique si fue entrante o saliente.',
        'direction_required' => 'Elija una dirección.',
    ],

    'badge' => [
        'confidential' => 'Confidencial',
        'followup_done' => 'Completado',
    ],

    'subtitle' => [
        'notes' => 'Tome notas rápidamente y encuéntrelas después, internas o de un cliente.',
    ],

    'storage' => [
        'all' => 'Todas las ubicaciones',
        'internal' => 'Interna',
        'customer' => 'Cliente',
    ],

    'filter' => [
        'search' => 'Buscar',
        'search_placeholder' => 'Asunto o contenido …',
        'all_customers' => 'Todos los clientes',
        'all_types' => 'Todos los tipos',
        'open_followups' => 'Seguimientos abiertos',
    ],

    'hint' => [
        'customer_not_published' => 'La nota aparece en la ficha del cliente, pero no en el portal de clientes.',
    ],

    'section' => [
        'more' => 'Más detalles',
    ],

    'empty' => 'Aún no hay notas de comunicación.',
    'empty_filtered' => 'No se encontraron notas.',
    'confirm_delete' => '¿Eliminar realmente esta nota de comunicación?',
    'confirm_publish' => '¿Hacer realmente visible esta nota para el cliente?',
];
