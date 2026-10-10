<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : zammad.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Zammad',
    'intro' => 'Los tickets abiertos que ve el token de API llegan como tareas en WorkDiary — para el registro de tiempos, los justificantes y la facturación. Con «Solo grupos asignados», solo los grupos asignados a un proyecto. El sistema de tickets sigue siendo la referencia; volver a importar nunca crea duplicados.',

    'health' => [
        'ok' => 'Conectado',
        'failing' => 'Inaccesible',
        'inactive' => 'Inactivo',
    ],

    'action' => [
        'sync' => 'Importar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
        'switch_target' => 'Cambiar destino',
    ],

    'connection' => [
        'heading' => 'Conexión',
    ],

    'field' => [
        'name' => 'Etiqueta',
        'base_url' => 'URL de la instancia',
        'api_token' => 'Token de API',
        'token_keep' => '•••••••• (dejar sin cambios)',
        'token_help' => 'Zammad: Perfil → Acceso por token. Se almacena cifrado.',
        'webhook_secret' => 'Secreto del webhook (opcional)',
        'webhook_help' => 'Secreto compartido para la firma del webhook (X-Hub-Signature). Vacío = webhook desactivado, solo sondeo.',
        'webhook_url' => 'Dirección del webhook (introdúzcala en Zammad en Webhook como punto de conexión, con el secreto como token de firma HMAC SHA1)',
        'default_project' => 'Proyecto predeterminado',
        'no_project' => '— sin proyecto (global) —',
        'active' => 'Activo',
        'resolved_state' => 'Retorno de estado (estado objetivo)',
        'resolved_state_help' => 'Opcional: estado objetivo del ticket al completar la tarea (p. ej. «closed»). Vacío = desactivado.',
        'time_unit' => 'Imputación de tiempo en el ticket',
        'time_unit_off' => 'Desactivado',
        'time_unit_minute' => 'Minutos',
        'time_unit_hour' => 'Horas',
        'time_unit_help' => 'Opcional: imputa en el ticket, como registro de tiempo, los tiempos registrados en tareas vinculadas. La unidad debe coincidir con la unidad de registro de tiempo de Zammad. Desactivado = sin imputación.',
        'allow_private_network' => 'Permitir direcciones privadas/internas',
        'allow_private_network_help' => 'Actívelo solo si Zammad está en su propia red (p. ej. 192.168.x.x). La acción queda auditada y solo surte efecto si el operador lo permite.',
    ],

    'queue' => [
        'heading' => 'Cola → proyecto',
        'help' => 'Asigna grupos de Zammad (ID de grupo) a un proyecto de WorkDiary. Sin coincidencia se aplica el proyecto predeterminado; de lo contrario, la tarea se crea de forma global.',
        'group_id' => 'ID de grupo',
        'limited' => 'Solo grupos asignados',
        'limited_help' => 'Activado: solo llegan como tareas los tickets de los grupos asignados aquí a un proyecto. Desactivado: todos los tickets que ve el token de API.',
    ],

    'flash' => [
        'saved' => 'Conexión de Zammad guardada.',
        'sync_done' => 'Importación de tickets iniciada.',
        'disconnected' => 'Conexión de Zammad desconectada. Las tareas y los vínculos se conservan.',
        'no_connection' => 'No hay ninguna conexión de Zammad activa.',
        'invalid_url' => 'La URL de la instancia debe empezar por http:// o https://.',
        'token_required' => 'Una conexión nueva requiere un token de API.',
        'private_url_blocked' => 'La URL de la instancia apunta a una dirección privada/interna. Para un Zammad en su propia red, active la autorización de direcciones privadas.',
        'helpdesk_required' => 'Los tickets de servicio requieren el módulo Helpdesk.',
        'queue_required' => 'Elija una cola.',
        'target_switched' => 'Destino de los tickets cambiado.',
    ],

    'target' => [
        'heading' => 'Destino de los tickets',
        'help' => 'Determina si los tickets nuevos llegan como tareas o como tickets de servicio de una cola. Los tickets ya importados permanecen donde están. Los conflictos de asignación abiertos en la bandeja de conciliación impiden el cambio.',
        'current' => 'Actualmente',
        'task' => 'Tareas',
        'service_ticket' => 'Tickets de servicio',
        'service_ticket_in' => 'Tickets de servicio en «:queue»',
        'field' => 'Tickets nuevos como',
        'queue' => 'Cola',
        'queue_help' => 'Solo para tickets de servicio. Después Zammad gestiona los tickets de esta cola.',
        'no_queue' => '— elegir cola —',
        'helpdesk_hint' => 'Los tickets de servicio requieren el módulo Helpdesk.',
        'no_queues_hint' => 'Todavía no hay ninguna cola. Créela en Service desk → Colas.',
        'confirm_title' => 'Cambiar el destino de los tickets',
        'confirm' => 'A partir de entonces, los tickets nuevos llegan por la vía elegida; los ya importados permanecen donde están. El cambio queda registrado.',
    ],

    'guard' => [
        'subject' => 'La URL de la instancia',
        'private_hint' => 'Para un Zammad en su propia red, la autorización de direcciones privadas debe estar activada en la conexión.',
    ],

    'resolution' => [
        'note' => 'Resuelto en WorkDiary.',
    ],
];
