<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Terminales de fichaje',
    'intro' => 'Terminales RFID/NFC fijos permiten a los empleados sin dispositivo de empresa fichar entrada y salida. Los eventos entran en la misma lógica de presencia que los fichajes por navegador (correcciones, informes). Los tokens de dispositivo y los identificadores de credencial se almacenan solo como hash.',

    'new_heading' => 'URL de ingesta del terminal',
    'new_hint' => 'Introdúcela en el terminal ahora — el token se muestra solo esta vez.',

    'terminals_heading' => 'Terminales',
    'no_terminals' => 'Aún no hay ningún terminal registrado.',
    'badges_heading' => 'Credenciales',
    'no_badges' => 'Aún no hay ninguna credencial asignada.',

    'field' => [
        'name' => 'Etiqueta',
        'name_placeholder' => 'p. ej. Nave Norte',
        'site' => 'Emplazamiento',
        'no_site' => '— sin emplazamiento —',
    ],

    'badge' => [
        'user' => 'Empleado',
        'label' => 'Etiqueta',
        'uid' => 'Identificador de credencial',
        'uid_placeholder' => 'UID RFID/NFC',
        'uid_help' => 'Se almacena solo como hash (sin identificador en claro).',
        'validity' => 'Validez',
        'valid_from' => 'Válido desde',
        'valid_until' => 'Válido hasta',
        'outside_validity' => 'fuera de validez',
    ],

    'action' => [
        'register' => 'Registrar',
        'disable' => 'Desactivar',
        'assign' => 'Asignar',
        'revoke' => 'Revocar',
        'rotate' => 'Rotar token',
        'rotate_help' => 'Genera un nuevo token de dispositivo — el antiguo deja de ser válido de inmediato.',
    ],

    'col' => [
        'status' => 'Estado',
        'status_display' => 'Indicador de estado',
        'last_seen' => 'Visto por última vez',
    ],

    'status_display' => [
        'on' => 'Activado',
        'off' => 'Desactivado',
        'help' => 'Muestra saldo/vacaciones restantes en el dispositivo tras fichar (visible para terceros) — desactivado por defecto.',
    ],

    'buffer' => [
        'label' => 'Búfer',
        'help' => 'Eventos sin conexión notificados por el terminal aún no transmitidos.',
    ],

    'status' => [
        'active' => 'Activo',
        'inactive' => 'Desactivado',
        'revoked' => 'Revocado',
    ],

    'flash' => [
        'registered' => 'Terminal registrado.',
        'terminal_disabled' => 'Terminal desactivado.',
        'badge_assigned' => 'Credencial asignada.',
        'badge_revoked' => 'Credencial revocada.',
        'badge_taken' => 'Este identificador de credencial ya está asignado.',
        'token_rotated' => 'Token de dispositivo rotado — nueva URL de ingesta visible una sola vez.',
        'status_enabled' => 'Indicador de estado activado.',
        'status_disabled' => 'Indicador de estado desactivado.',
    ],
];
