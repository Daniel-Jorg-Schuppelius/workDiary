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
    'new_hint' => 'Introdúzcala en el terminal ahora — el token se muestra solo esta vez.',

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
    'kiosk' => [
        'pin_toggle' => '¿Olvidó la tarjeta? Fiche con PIN',
        'pin_submit' => 'Fichar',
        'heading' => 'Dirección del quiosco',
        'hint' => 'Ábrala en el navegador de una tableta y la tableta se convierte en terminal de fichaje. Contiene el mismo token; solo se muestra una vez.',
        'title' => 'Terminal de fichaje',
        'intro' => 'Acerque su tarjeta al lector.',
        'mode' => 'Tipo de fichaje',
        'mode_work' => 'Entrada / Salida',
        'mode_break' => 'Pausa',
        'badge_label' => 'Tarjeta',
        'nfc_start' => 'Usar el NFC de este dispositivo',
        'nfc_active' => 'NFC leyendo',
        'flex_balance' => 'Horario flexible:',
        'status' => [
            'invalid_pin' => 'Número de personal o PIN no válido',
            'clocked_in' => 'Entrada registrada',
            'clocked_out' => 'Salida registrada',
            'break_started' => 'Pausa iniciada',
            'break_ended' => 'Pausa finalizada',
            'noop' => 'No hay asistencia abierta',
            'skipped' => 'Ya registrado',
            'unknown_badge' => 'Tarjeta desconocida',
            'rejected' => 'Fichaje rechazado',
            'invalid_token' => 'Terminal bloqueado',
            'unavailable' => 'Ahora no es posible fichar',
            'network' => 'Sin conexión: inténtelo de nuevo',
            'nfc_unavailable' => 'NFC no está disponible en este dispositivo',
            'error' => 'Error al fichar',
        ],
    ],
    'checkpoint' => [
        'heading' => 'Puntos de check-in (QR/NFC)',
        'intro' => 'Un código en la ubicación o el vehículo: el personal lo escanea con su dispositivo y ficha con su sesión. La misma dirección puede grabarse en una etiqueta NFC.',
        'empty' => 'Aún no hay puntos de check-in.',
        'action' => [
            'create' => 'Crear punto de check-in',
            'qr' => 'Imprimir código QR',
            'enable' => 'Activar',
        ],
        'field' => [
            'kind' => 'Tipo',
            'location' => 'Ubicación / vehículo',
            'vehicle' => 'Vehículo',
            'radius' => 'Radio (m)',
            'location_check' => 'Comprobación de ubicación (opcional)',
            'latitude' => 'Latitud',
            'longitude' => 'Longitud',
        ],
        'help' => [
            'site' => 'Solo para el tipo «Ubicación».',
            'vehicle' => 'Obligatorio para el tipo «Vehículo».',
            'location_check' => 'Un código puede fotografiarse. Con radio, el dispositivo debe estar cerca al fichar; sin coordenadas propias se usan las de la ubicación. La posición no se guarda.',
        ],
        'error' => [
            'radius_without_center' => 'El radio necesita una ubicación: introduzca coordenadas o elija una ubicación con coordenadas.',
            'vehicle' => 'No se encontró el vehículo.',
        ],
        'flash' => [
            'created' => 'Punto de check-in creado.',
            'enabled' => 'Punto de check-in activado.',
            'disabled' => 'Punto de check-in bloqueado.',
        ],
        'qr' => [
            'alt' => 'Código QR para el check-in «:name»',
            'hint' => 'Escanee con el móvil, inicie sesión y confirme entrada o salida.',
            'nfc_hint' => 'Para una etiqueta NFC, grabe esta dirección como dirección web (URL) con una app NFC.',
        ],
    ],
    'pin' => [
        'heading' => 'PIN de terminal',
        'intro' => '¿Olvidó la tarjeta? Con número de personal y PIN se puede fichar igualmente en el terminal y el quiosco. Solo se guarda un hash; tras 5 intentos fallidos el PIN se bloquea 15 minutos.',
        'empty' => 'Aún no hay PIN asignados.',
        'action' => [
            'set' => 'Establecer PIN',
            'unlock' => 'Desbloquear',
            'remove' => 'Eliminar',
        ],
        'field' => [
            'pin' => 'PIN',
            'pin_confirmation' => 'Repetir PIN',
            'personnel_number' => 'Número de personal',
        ],
        'help' => [
            'dialog' => 'De 4 a 8 dígitos. La persona conoce el PIN por usted; después ya no se puede consultar.',
            'personnel_number' => 'Solo personas con número de personal: es la segunda parte del acceso en el terminal.',
        ],
        'status' => [
            'locked_until' => 'bloqueado hasta las :time',
        ],
        'confirm' => [
            'remove' => '¿Eliminar el PIN? La persona solo podrá fichar con tarjeta.',
        ],
        'error' => [
            'format' => 'El PIN debe tener entre :min y :max dígitos.',
            'personnel_number' => 'La persona no tiene número de personal: sin él el PIN no sirve en el terminal.',
        ],
        'flash' => [
            'set' => 'PIN establecido.',
            'unlocked' => 'PIN desbloqueado.',
            'removed' => 'PIN eliminado.',
        ],
    ],
];
