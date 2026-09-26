<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : js.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dialog' => [
        'check_input' => 'Compruebe los datos introducidos.',
        'save_failed' => 'No se pudo guardar el diálogo.',
        'load_failed' => 'No se pudo cargar el diálogo.',
        'loading' => 'Cargando…',
        'open_in_new_tab' => 'Abrir la página en una pestaña nueva',
        'switch_to_new' => 'Cambiar al nuevo modo',
        'switch_to_legacy' => 'Cambiar al modo heredado',
    ],
    'schedule' => [
        'move_failed' => 'Error al mover.',
        'suggest_failed' => 'No se pudieron cargar las sugerencias.',
    ],
    // Dienstplan-Oberflaeche (MVP-797): war zuvor fest verdrahtetes Deutsch.
    'schedule_ui' => [
        'shift_edit' => 'Editar turno',
        'shift_create' => 'Crear turno',
        'shift_delete_confirm' => '¿Eliminar realmente este turno?',
        'shift_type_edit' => 'Editar tipo de turno',
        'shift_type_create' => 'Crear tipo de turno',
        'shift_type_delete_confirm' => '¿Eliminar realmente este tipo de turno?',
        'save' => 'Guardar',
        'delete' => 'Eliminar',
        'close' => 'Cerrar',
        'save_failed' => 'Error al guardar.',
        'delete_failed' => 'Error al eliminar.',
        'publish_failed' => 'Error al publicar.',
        'confirm_failed' => 'Error al confirmar.',
        'suggestions_title' => 'Sugerencias de personal',
        'col_employee' => 'Empleado',
        'col_score' => 'Puntuación',
        'col_reason' => 'Motivo',
        'type_active_yes' => 'sí',
        'type_active_no' => 'no',
    ],
    'bulk' => [
        'select_one' => 'Seleccione primero al menos una entrada.',
    ],
    'design' => [
        'inheritance' => '«:base» · :inherited/:total heredados, :own sobrescritos',
    ],
    // Chat-Oberflaeche (MVP-798): angepinnte Nachrichten werden per JSON geladen.
    'chat' => [
        'pinned_failed' => 'No se pudieron cargar los mensajes fijados.',
        'pinned_empty' => 'No hay mensajes fijados.',
    ],
    'kanban' => [
        'invalid_move' => 'Este cambio de estado no está previsto en el flujo de trabajo del pedido.',
        'not_allowed' => 'No tiene permiso para esta acción del pedido.',
        'handover_via_order' => 'La recepción requiere un protocolo firmado y se realiza directamente en el pedido.',
        'no_targets' => 'Actualmente no hay ningún movimiento permitido para esta tarjeta.',
    ],
    'entry_bar' => [
        'options_failed' => 'No se pudieron cargar las tareas/pedidos.',
    ],
    'http' => [
        'session_expired' => 'Su sesión ha caducado — la página se recargará.',
    ],
    // KI-Tagvorschläge im Tag-Picker (Feature 143, MVP-711)
    'ai' => [
        'tags_no_text' => 'Introduzca primero un contenido — la IA sugiere etiquetas a partir del texto.',
        'tags_none' => 'Ninguna etiqueta existente coincide con el texto.',
        'tags_failed' => 'Sugerencia de etiquetas IA no posible: :message',
        'tags_loading' => 'La IA busca etiquetas adecuadas …',
    ],
    // Tastenkürzel-Übersicht (Feature 037, MVP-721): Labels der Registry resources/js/shortcuts.js
    'shortcuts' => [
        'help' => 'Abrir la ayuda contextual de la página actual',
        'title' => 'Atajos de teclado',
        'scope' => [
            'global' => 'Global',
            'navigation' => 'Navegación',
            'search' => 'Búsqueda',
        ],
        'search' => 'Abrir la búsqueda global',
        'shortcuts' => 'Mostrar este resumen',
        'escape' => 'Cerrar diálogo o búsqueda',
        'search_move' => 'Moverse por los resultados de búsqueda',
        'search_open' => 'Abrir resultado',
        'go_diary' => 'Ir al diario',
        'go_customers' => 'Ir a los clientes',
        'go_projects' => 'Ir a los proyectos',
        'new_entry' => 'Nueva entrada',
        'then' => 'luego',
    ],
    'quiz' => [
        'progress' => ':answered de :total respondidas',
    ],
    // NFC-Scan (MVP-903).
    'nfc' => [
        'hold' => 'Acerque la etiqueta al dispositivo …',
        'empty' => 'La etiqueta no contiene ningún enlace.',
        'failed' => 'NFC no disponible.',
        'written' => 'Etiqueta escrita.',
    ],
];
