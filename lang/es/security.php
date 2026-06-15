<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Seguridad',
    ],

    'subtitle' => 'Resumen de solo lectura del estado relevante para la seguridad: sesiones activas, tokens de API, integraciones externas, últimas exportaciones y accesos de soporte.',

    'scope' => [
        'label' => 'Ámbito',
        'platform' => 'A nivel de plataforma',
    ],

    'privacy_notice' => 'Esta página solo muestra metadatos. Nunca se muestran valores de tokens, hashes, secretos, contraseñas ni contenidos de sesión. Todos los datos permanecen locales.',

    'deferred_notice' => 'Las ejecuciones automáticas de eliminación y conservación no forman parte de este resumen y se realizarán en un paso posterior (Función 016, «Más adelante»).',

    'section' => [
        'sessions' => 'Sesiones activas',
        'tokens' => 'Tokens de API',
        'integrations' => 'Integraciones externas',
        'exports' => 'Últimas exportaciones',
        'support_access' => 'Últimos accesos de soporte',
        'two_factor' => 'Autenticación de dos factores',
        'encryption' => 'Cifrado (en reposo)',
    ],

    'field' => [
        'user' => 'Usuario',
        'guest' => 'Sin iniciar sesión',
        'ip' => 'Dirección IP',
        'user_agent' => 'Agente de usuario',
        'last_activity' => 'Última actividad',
        'sessions_total' => 'Sesiones en total',
        'sessions_active' => 'De las cuales activas (< 2 h)',
        'token_name' => 'Nombre',
        'abilities' => 'Permisos',
        'last_used_at' => 'Último uso',
        'expires_at' => 'Expira',
        'created_at' => 'Creado',
        'tokens_total' => 'Tokens en total',
        'plugins_active' => 'Plugins activos',
        'external_references' => 'Referencias externas',
        'export_kind' => 'Tipo',
        'export_subject' => 'Objeto',
        'format' => 'Formato',
        'status' => 'Estado',
        'rows' => 'Registros',
        'event' => 'Evento',
        'subject' => 'Objeto',
        'users_total' => 'Usuarios en total',
        'users_with_2fa' => 'Con 2FA activa',
        'credentials' => 'Factores confirmados',
        'coverage' => 'Cobertura',
        'encrypted_fields' => 'Campos cifrados',
        'table' => 'Tabla',
        'fields' => 'Campos',
    ],

    'export' => [
        'kind' => [
            'data_transfer' => 'Transferencia de datos',
            'time' => 'Exportación de tiempos',
        ],
    ],

    'status' => [
        'active' => 'activo',
        'inactive' => 'inactivo',
        'app_key_set' => 'APP_KEY definida',
        'app_key_missing' => 'APP_KEY ausente',
    ],

    'hint' => [
        'sessions_driver' => 'Controlador de sesión «:driver» — no hay resumen de base de datos disponible. Solo el controlador «database» proporciona una lista de sesiones.',
        'tokens_no_secret' => 'Solo se muestran metadatos — nunca el valor del token ni su hash.',
        'support_access' => 'Origen: registro de auditoría, prefijo de evento «support.» (véase los principios de acceso de soporte).',
        'two_factor' => 'Recuento simple de factores confirmados — no se lee ningún secreto.',
        'encryption' => 'Estos campos se cifran mediante «php artisan :command». El cifrado depende de la APP_KEY.',
    ],

    'empty' => [
        'sessions' => 'No se encontraron sesiones.',
        'tokens' => 'No hay tokens de API activos.',
        'integrations' => 'No hay integraciones externas activas.',
        'exports' => 'Aún no se han registrado exportaciones.',
        'support_access' => 'No se han registrado accesos de soporte.',
    ],

    'generated_at' => 'Generado: :at',
];
