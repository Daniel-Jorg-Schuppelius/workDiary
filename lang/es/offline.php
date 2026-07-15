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
    'subtitle' => 'Acciones registradas sin conexión en este dispositivo — pendientes o rechazadas.',
    'notice' => 'Esta lista se guarda solo en este dispositivo. Las entradas pendientes se sincronizan automáticamente al recuperar la conexión; las rechazadas pueden aplicarse de nuevo o descartarse.',
    'empty' => 'No hay cambios sin conexión en este dispositivo.',
    'section' => [
        'pending' => 'Pendientes',
        'rejected' => 'Rechazadas',
    ],
    'type' => [
        'clock_in' => 'Fichaje de entrada',
        'clock_out' => 'Fichaje de salida',
        'comment' => 'Comentario de la orden',
        'form' => 'Formulario',
    ],
    'action' => [
        'retry' => 'Aplicar de nuevo',
        'discard' => 'Descartar',
    ],
];
