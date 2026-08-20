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
    'subtitle' => 'Acciones registradas sin conexión en este dispositivo: pendientes, en conflicto o rechazadas.',
    'notice' => 'Esta lista solo existe en este dispositivo. Las entradas pendientes se transfieren automáticamente en cuanto hay conexión; las rechazadas pueden reenviarse o descartarse. Los conflictos requieren una decisión: otra persona modificó el mismo registro.',
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
    ],
    'action' => [
        'retry' => 'Aplicar de nuevo',
        'discard' => 'Descartar',
        'take_server' => 'Conservar la otra versión',
        'force_local' => 'Enviar mi versión',
    ],
    'conflict_hint' => 'Estado del servidor: :server',
    'photos_queued' => 'Fotos en cola: :count',
];
