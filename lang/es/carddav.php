<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : carddav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CardDAV',
    'intro' => 'Los contactos se leen desde una libreta de direcciones CardDAV autoalojada (Nextcloud/Radicale/Baïkal) y se incorporan a la bandeja de integración como propuestas de asignación — sin fusiones automáticas ni escrituras en los datos de clientes. Las tarjetas sin cambios se omiten (UID+ETag).',
    'description' => 'Lee contactos de una libreta de direcciones CardDAV (RFC 6352) y los incorpora a la bandeja de integración como propuestas de asignación — solo lectura, on-premise, sin cuenta de Microsoft/Google.',

    'health' => [
        'ok' => 'Conectado',
        'failing' => 'Inaccesible',
        'inactive' => 'Inactivo',
        'no_connection' => 'No hay ninguna conexión CardDAV configurada.',
        'inactive_or_incomplete' => 'La conexión CardDAV está desactivada o incompleta.',
        'unreachable' => 'Servidor CardDAV inaccesible o credenciales no válidas.',
        'error' => 'Error de CardDAV (:class).',
        'last_error' => 'Último error: :error',
    ],

    'action' => [
        'discover' => 'Buscar libretas de direcciones',
        'choose_addressbook' => 'Usar esta libreta',
        'sync' => 'Sincronizar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'connection' => [
        'heading' => 'Conexión',
    ],

    'addressbook' => [
        'heading' => 'Libreta de direcciones',
        'current' => 'Fuente de sincronización actual: :name',
        'hint' => 'Use «Buscar libretas de direcciones» para consultar el servidor y elija después una libreta como fuente de sincronización.',
    ],

    'status' => [
        'last_synced' => 'Última sincronización :at.',
    ],

    'field' => [
        'name' => 'Denominación',
        'base_url' => 'URL base DAV',
        'base_url_help' => 'Nextcloud: .../remote.php/dav — Radicale/Baïkal: raíz del servidor. El descubrimiento sigue la RFC 6764 (.well-known/carddav).',
        'username' => 'Nombre de usuario',
        'app_password' => 'Contraseña de aplicación',
        'password_keep' => '•••••••• (dejar sin cambios)',
        'password_help' => 'Con 2FA activada (p. ej. Nextcloud) es obligatoria una contraseña de aplicación. Se guarda cifrada.',
        'allow_private_network' => 'Permitir direcciones privadas/internas',
        'allow_private_network_help' => 'Actívelo solo si el servidor CardDAV está en su propia red (p. ej. 192.168.x.x). La acción queda auditada.',
        'active' => 'Activo',
    ],

    'flash' => [
        'saved' => 'Conexión CardDAV guardada.',
        'invalid_url' => 'La URL base debe empezar por http:// o https://.',
        'private_url_blocked' => 'La URL base apunta a una dirección privada/interna. Active el permiso de direcciones privadas para un servidor en su propia red.',
        'password_required' => 'Para una conexión nueva se requiere una contraseña de aplicación.',
        'no_connection' => 'No hay ninguna conexión CardDAV activa disponible.',
        'discovery_failed' => 'La búsqueda de libretas de direcciones falló — servidor inaccesible o credenciales no válidas.',
        'no_addressbooks' => 'No se encontraron libretas de direcciones en el servidor.',
        'discovered' => ':count libretas de direcciones encontradas — elija una fuente de sincronización.',
        'addressbook_not_discovered' => 'Ejecute primero «Buscar libretas de direcciones» y elija una libreta encontrada.',
        'addressbook_saved' => 'Libreta de direcciones establecida como fuente de sincronización.',
        'not_syncable' => 'Sincronización imposible — conexión inactiva, con fallos o sin libreta seleccionada.',
        'sync_done' => 'Sincronización iniciada. Los contactos nuevos aparecerán como propuestas en la bandeja de asignación.',
        'disconnected' => 'Conexión CardDAV desconectada. Las propuestas ya incorporadas se conservan.',
    ],
];
