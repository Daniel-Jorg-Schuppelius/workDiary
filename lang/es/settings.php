<?php

return [
    'tabs' => [
        'pagination' => 'Listas',
        'invoicing' => 'Facturación',
        'uploads' => 'Subidas de archivos',
        'validation' => 'Límites de entrada',
        'notifications' => 'Notificaciones',
        'ui' => 'Interfaz',
        'routing' => 'Enrutamiento y mapas',
    ],
    'hint' => 'Dejar vacío para usar el valor predeterminado del sistema.',
    'pagination' => [
        'heading' => 'Tamaños de página',
        'description' => 'Número de elementos por página en las listas.',
        'timesheets' => 'Hojas de horas',
        'duty_plans' => 'Planes de turnos',
        'customers' => 'Clientes',
        'customer_search' => 'Búsqueda de clientes (autocompletado)',
        'customer_attachments' => 'Adjuntos de cliente',
        'organizations' => 'Organizaciones',
        'tours' => 'Rutas',
        'vehicles' => 'Vehículos',
        'tags' => 'Etiquetas',
        'archive' => 'Archivo',
        'dashboard_recent' => 'Panel: elementos recientes',
    ],
    'invoicing' => [
        'heading' => 'Valores predeterminados de facturación',
        'description' => 'Valores precargados al crear una nueva factura.',
        'default_tax_rate' => 'Tipo impositivo predeterminado (%)',
        'default_currency' => 'Moneda predeterminada (ISO-4217)',
        'time_unit' => 'Unidad de tiempo para las posiciones',
    ],
    'uploads' => [
        'heading' => 'Límites de tamaño de subida (KB)',
        'description' => 'Tamaños máximos de subida, en kilobytes.',
        'csv_import_kb' => 'Importación CSV',
        'customer_attachment_kb' => 'Adjunto de cliente',
    ],
    'validation' => [
        'heading' => 'Límites de entrada',
        'description' => 'Límites de caracteres y de rango para los campos del formulario.',
        'attendance' => [
            'heading' => 'Presencia',
            'note_max' => 'Nota, caracteres máx.',
            'device_max' => 'ID de dispositivo, caracteres máx.',
            'break_minutes_max' => 'Pausa, minutos máx.',
        ],
        'tag' => [
            'heading' => 'Etiquetas',
            'name_max' => 'Nombre de etiqueta, caracteres máx.',
        ],
        'comment' => [
            'heading' => 'Comentarios',
            'body_max' => 'Cuerpo del comentario, caracteres máx.',
        ],
        'duty_plan' => [
            'heading' => 'Planes de turnos',
            'note_max' => 'Nota, caracteres máx.',
        ],
    ],
    'notifications' => [
        'heading' => 'Notificaciones push',
        'description' => 'Comportamiento de los mensajes push.',
        'push' => [
            'body_truncate' => 'Vista previa del mensaje, caracteres máx.',
        ],
    ],
    'ui' => [
        'heading' => 'Comportamiento de la interfaz',
        'description' => 'Comportamiento visual e interactivo de la interfaz.',
        'calendar' => [
            'heading' => 'Calendario',
            'slot_minutes' => 'Duración de los espacios en minutos',
        ],
        'dashboard' => [
            'heading' => 'Panel',
            'recent_limit' => 'Número de elementos recientes',
        ],
        'search' => [
            'heading' => 'Búsqueda',
            'results_limit' => 'Límite de resultados predeterminado',
        ],
    ],
    'reset' => 'Restablecer a predeterminado',
    'placeholder_default' => 'Predeterminado :value',
    'routing' => [
        'nominatim' => [
            'heading' => 'Nominatim (geocodificación)',
            'base_url' => 'URL base',
            'email' => 'Correo de contacto',
            'rate_limit_per_sec' => 'Solicitudes por segundo',
        ],
        'osrm' => [
            'heading' => 'OSRM (enrutamiento)',
            'base_url' => 'URL base',
            'profile' => 'Perfil (p. ej. driving)',
            'timeout' => 'Tiempo de espera (segundos)',
        ],
        'tiles' => [
            'heading' => 'Teselas de mapa',
            'url' => 'Plantilla de URL de tesela',
            'max_zoom' => 'Zoom máximo',
        ],
    ],
];
