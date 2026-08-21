<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : caldav.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'CalDAV',
    'intro' => 'Las citas de WorkDiary se publican en un calendario CalDAV externo (Nextcloud/ownCloud) — on-premise, sin cuenta de Microsoft ni de Google. WorkDiary sigue siendo la referencia; las citas canceladas desaparecen allí y las ejecuciones repetidas nunca crean duplicados.',

    'health' => [
        'ok' => 'Conectado',
        'failing' => 'Inaccesible',
        'inactive' => 'Inactivo',
    ],

    'action' => [
        'publish' => 'Publicar ahora',
        'disconnect' => 'Desconectar',
        'save' => 'Guardar',
    ],

    'connection' => [
        'heading' => 'Conexión',
    ],

    // Titel der Inbox-Einträge des Kalenderimports — ein remote
    // gelöschter Termin wird gemeldet, nicht still nachgezogen.
    'import' => [
        'deleted_title' => 'Cita eliminada en el calendario CalDAV',
    ],

    'field' => [
        'name' => 'Etiqueta',
        'base_url' => 'URL base DAV',
        'base_url_help' => 'Nextcloud: .../remote.php/dav (sin la ruta del calendario).',
        'username' => 'Nombre de usuario',
        'app_password' => 'Contraseña de aplicación',
        'password_keep' => '•••••••• (dejar sin cambios)',
        'password_help' => 'Nextcloud: Ajustes → Seguridad → Contraseña de aplicación. Se almacena cifrada.',
        'calendar_path' => 'Ruta del calendario (colección)',
        'calendar_path_help' => 'Relativa a la URL base, p. ej. calendars/team/turnos.',
        'active' => 'Activo',
        // MVP-610b: Rückimport ist Opt-in — er ändert Daten.
        'two_way' => 'Bidireccional: importar los cambios externos como propuestas',
        'two_way_help' => 'Reimportación de la colección del calendario mediante sync-collection (RFC 6578) o, en su defecto, sobre una ventana temporal con comparación de ETag — las citas externas nuevas, los cambios externos en las publicadas y las eliminaciones llegan como casos a la bandeja de integración (nunca una creación a ciegas).',
        'scopes' => 'Contenido publicado',
        'scope_events' => 'Eventos',
        'scope_schedule' => 'Turnos y vacaciones',
        'scopes_help' => 'Qué contenido se publica en esta colección. Sin selección, solo eventos.',
    ],

    'flash' => [
        'saved' => 'Conexión CalDAV guardada.',
        'publish_done' => 'Publicación iniciada.',
        'disconnected' => 'Conexión CalDAV desconectada. Las citas ya publicadas se conservan externamente.',
        'no_connection' => 'No hay ninguna conexión CalDAV activa.',
        'invalid_url' => 'La URL base debe empezar por http:// o https://.',
        'password_required' => 'Una conexión nueva requiere una contraseña de aplicación.',
    ],
];
