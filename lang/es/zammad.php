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
    'intro' => 'Los tickets de un grupo de Zammad asignado llegan como tareas en WorkDiary — para el registro de tiempos, los justificantes y la facturación. El sistema de tickets sigue siendo la referencia; volver a importar nunca crea duplicados.',

    'health' => [
        'ok' => 'Conectado',
        'failing' => 'Inaccesible',
        'inactive' => 'Inactivo',
    ],

    'action' => [
        'sync' => 'Importar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
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
        'default_project' => 'Proyecto predeterminado',
        'no_project' => '— sin proyecto (global) —',
        'active' => 'Activo',
        'resolved_state' => 'Retorno de estado (estado objetivo)',
        'resolved_state_help' => 'Opcional: estado objetivo del ticket al completar la tarea (p. ej. «closed»). Vacío = desactivado.',
    ],

    'queue' => [
        'heading' => 'Cola → proyecto',
        'help' => 'Asigna grupos de Zammad (ID de grupo) a un proyecto de WorkDiary. Sin coincidencia se aplica el proyecto predeterminado; de lo contrario, la tarea se crea de forma global.',
        'group_id' => 'ID de grupo',
    ],

    'flash' => [
        'saved' => 'Conexión de Zammad guardada.',
        'sync_done' => 'Importación de tickets iniciada.',
        'disconnected' => 'Conexión de Zammad desconectada. Las tareas y los vínculos se conservan.',
        'no_connection' => 'No hay ninguna conexión de Zammad activa.',
        'invalid_url' => 'La URL de la instancia debe empezar por http:// o https://.',
        'token_required' => 'Una conexión nueva requiere un token de API.',
    ],
    'resolution' => [
        'note' => 'Resuelto en WorkDiary.',
    ],
];
