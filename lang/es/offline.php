<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Cambios sin conexión',
    'subtitle' => 'Acciones registradas sin conexión: pendientes, en conflicto o rechazadas.',
    'notice' => 'Las entradas pendientes y rechazadas solo existen en este dispositivo; las pendientes se transfieren automáticamente en cuanto hay conexión. Los conflictos requieren su decisión: los datos cambiaron desde el registro (por ejemplo el fichaje, o el día se cerró entretanto) o falta un dato obligatorio. Los conflictos abiertos también aparecen aquí cuando surgieron en otro dispositivo.',
    'empty' => 'No hay cambios sin conexión en este dispositivo.',
    'section' => [
        'pending' => 'Pendientes',
        'rejected' => 'Rechazadas',
        'conflict' => 'Conflictos',
    ],
    'type' => [
        'clock_in' => 'Fichaje de entrada',
        'clock_out' => 'Fichaje de salida',
        'comment' => 'Comentario de la orden',
        'form' => 'Formulario',
        'attendance_correct' => 'Corrección de fichaje',
        'time_entry' => 'Tiempo de proyecto',
        'material_usage' => 'Material',
        'learning_unit' => 'Unidad de aprendizaje completada',
        'inventory_count' => 'Escaneo de inventario',
        'tour_arrive' => 'Parada de ruta: llegada',
        'tour_depart' => 'Parada de ruta: salida',
        'patrol_scan' => 'Punto de control',
        'takeoff_line' => 'Línea de medición',
    ],
    'action' => [
        'retry' => 'Aplicar de nuevo',
        'discard' => 'Descartar',
        'take_server' => 'Conservar la otra versión',
        'force_local' => 'Enviar mi versión',
        'edit' => 'Editar',
        'send_edited' => 'Enviar editado',
        'cancel_edit' => 'Cancelar',
    ],
    'conflict_hint' => 'Estado del servidor: :server',
    'photos_queued' => 'Fotos en cola: :count',
    // Konflikte entscheiden (MVP-1595).
    'field' => [
        'missing' => 'Falta',
        'lock' => 'Bloqueo',
    ],
    'remote_hint' => 'Registrado en otro dispositivo: el contenido está allí. Aquí solo puede descartar el conflicto.',
    'resolve_failed' => 'No se pudo transmitir la decisión. Vuelva a intentarlo con conexión.',
    'unresolved' => 'abierto',
    'flash' => [
        'resolved' => 'Conflicto resuelto.',
        'not_found' => 'Este conflicto ya no existe.',
    ],
];
