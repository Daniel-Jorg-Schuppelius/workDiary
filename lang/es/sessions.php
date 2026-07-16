<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Usuarios conectados',
    ],

    'subtitle' => 'Quién está conectado y dónde — sesiones activas y accesos API por usuario, con posibilidad de cerrar sesión de forma remota.',

    'privacy_notice' => 'Solo se muestran metadatos (IP, dispositivo, marcas de tiempo) — nunca el contenido de las sesiones ni los valores de los tokens.',

    'hint' => [
        'driver' => 'Las sesiones solo se pueden listar con el controlador de base de datos; controlador actual: :driver. Sin el controlador database no es posible el cierre de sesión remoto selectivo.',
        'terminals' => 'Los terminales de fichaje son dispositivos físicos (no un inicio de sesión de usuario). «Desactivar» bloquea el dispositivo, no cierra la sesión de ningún usuario.',
        'remote_support' => 'Sesiones de asistencia remota importadas — solo historial; no se pueden finalizar desde workDiary.',
    ],

    'stat' => [
        'users' => 'Usuarios',
        'online' => 'En línea',
        'sessions' => 'Sesiones',
        'tokens' => 'Tokens API',
    ],

    'badge' => [
        'online' => 'En línea',
        'this_device' => 'Este dispositivo',
    ],

    'section' => [
        'sessions' => 'Sesiones web/app',
        'tokens' => 'Tokens API',
        'devices' => 'Dispositivos de ubicación',
        'terminals' => 'Terminales de fichaje',
        'remote_support' => 'Asistencia remota reciente',
    ],

    'col' => [
        'device' => 'Dispositivo',
        'ip' => 'IP',
        'last_activity' => 'Última actividad',
        'name' => 'Nombre',
        'created' => 'Creado',
        'last_used' => 'Último uso',
        'action' => 'Acción',
        'terminal' => 'Terminal',
        'status' => 'Estado',
        'last_seen' => 'Visto por última vez',
        'provider' => 'Proveedor',
        'remote' => 'Identificador',
        'started' => 'Inicio',
        'ended' => 'Fin',
    ],

    'terminal' => [
        'inactive' => 'Desactivado',
        'offline' => 'Sin conexión',
    ],

    'last_login' => 'Último inicio de sesión',

    'live' => [
        'changed' => 'Las sesiones activas han cambiado.',
        'reload' => 'Recargar lista',
    ],

    'action' => [
        'revoke_all' => 'Cerrar sesión en todos los dispositivos',
        'revoke_session' => 'Cerrar sesión',
        'revoke_token' => 'Revocar',
        'revoke_device' => 'Desconectar',
        'deactivate_terminal' => 'Desactivar',
    ],

    'confirm' => [
        'revoke_all' => '¿Cerrar la sesión de :name en todos los dispositivos? Las sesiones existentes y «mantener sesión iniciada» se invalidarán.',
        'revoke_session' => '¿Cerrar realmente esta sesión de forma remota?',
        'revoke_token' => '¿Revocar realmente este token API?',
        'revoke_device' => '¿Desconectar realmente este dispositivo de ubicación?',
        'deactivate_terminal' => '¿Desactivar realmente el terminal ":name"? El dispositivo ya no podrá iniciar sesión.',
    ],

    'empty' => [
        'title' => 'No hay sesiones activas.',
        'description' => 'Actualmente no hay nadie conectado en esta organización.',
    ],

    'error' => [
        'own_current_session' => 'Tu propia sesión actual no se puede cerrar aquí — usa el cierre de sesión normal.',
        'session_gone' => 'La sesión ya no existe.',
        'token_gone' => 'El token ya no existe.',
        'device_gone' => 'El dispositivo ya no existe o ya está desconectado.',
    ],

    'flash' => [
        'session_revoked' => 'Sesión cerrada de forma remota.',
        'all_revoked' => ':name ha cerrado sesión en todos los dispositivos.',
        'token_revoked' => 'Token API revocado.',
        'device_revoked' => 'Dispositivo de ubicación desconectado.',
        'terminal_deactivated' => 'Terminal desactivado.',
    ],
];
