<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Informe de rondas',
        'subtitle' => 'Rondas por ruta y objeto en el periodo: previstas, puntuales, con retraso y omitidas.',
        'expected' => 'Puntos previstos',
        'onTime' => 'puntual',
        'late' => 'con retraso',
        'missed' => 'omitido',
        'note' => 'Motivo',
        'runs' => 'Rondas',
        'punctuality' => 'Puntualidad',
        'aborted' => 'interrumpidas',
        'allRoutes' => 'Todas las rutas',
        'allSites' => 'Todos los objetos',
        'empty' => 'No hay rondas en el periodo.',
        'summary' => ':runs rondas, :completed completadas y :aborted interrumpidas',
    ],
    'scan' => [
        'implausibleTime' => 'La hora de escaneo del dispositivo está fuera de la ronda; compruebe el reloj del dispositivo.',
    ],
    // Mantenimiento y desviaciones (MVP-1611).
    'route' => [
        'edit_title' => 'Editar ruta',
        'responsible' => 'Responsable de las desviaciones',
        'responsible_hint' => 'Recibe el punto abierto en caso de desviaciones. Vacío: la jefatura de equipo de la persona que realiza la ronda.',
        'updated' => 'Ruta guardada.',
        'deactivate_confirm' => '¿Desactivar la ruta? Una ronda en curso puede terminar; no se pueden iniciar nuevas.',
        'deactivated' => 'Ruta desactivada: ya no se puede iniciar.',
        'activated' => 'La ruta vuelve a estar activa.',
    ],
    'checkpoint' => [
        'edit_title' => 'Editar punto de control',
        'edit_hint' => 'Los cambios se aplican a escaneos futuros; los escaneos registrados conservan su valoración.',
        'updated' => 'Punto de control «:label» guardado.',
        'deactivate_confirm' => '¿Desactivar el punto de control? A partir de ahora ya no se espera ni se puede escanear; las rondas anteriores no cambian.',
        'deactivated_done' => 'Punto de control «:label» desactivado.',
        'deactivated' => 'Este punto de control está desactivado.',
        'inactive_badge' => 'desactivado',
    ],
    'deviation' => [
        'running_title' => 'La ronda «:route» presenta desviaciones',
        'late_line' => '«:label» fuera de la ventana (:delta min)',
        'missed_line' => ':count puntos de control vencidos sin escaneo',
        'overdue_title' => 'Ronda «:route» no finalizada',
        'overdue_line' => 'Duración prevista de :minutes minutos superada (inicio :start).',
    ],
];
