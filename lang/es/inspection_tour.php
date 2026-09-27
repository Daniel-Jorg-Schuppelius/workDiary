<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inspection_tour.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Prüfertouren (MVP-918).
return [
    'title' => 'Ruta de inspección',
    'subtitle' => 'Planifique como ruta las inspecciones pendientes de un inspector: cada cita se convierte en un pedido en la ubicación del equipo y la planificación optimiza el orden.',
    'inspector' => 'Inspector',
    'due_until' => 'Vence hasta',
    'date' => 'Fecha de la ruta',
    'select' => 'Seleccionar',
    'location' => 'Ubicación',
    'no_coordinates' => 'sin coordenadas',
    'no_coordinates_hint' => 'Sin coordenadas en el equipo, la parada permanece en la ruta pero no entra en el cálculo del recorrido.',
    'none' => 'No hay inspecciones abiertas de este inspector en el periodo.',
    'none_selected' => 'Seleccione al menos una inspección abierta.',
    'unavailable' => 'La planificación de rutas no está activa en su organización; las rutas de inspección requieren el módulo de planificación.',
    'plan' => 'Planificar ruta',
    'planned' => ':count inspecciones planificadas como ruta.',
    'entry_title' => 'Inspección :profile – :asset',
    'entry_content' => 'Inspección de la ruta de inspección, vence el :due.',
];
