<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : travel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'vehicle' => [
        'company' => 'Coche de empresa',
        'private' => 'Coche privado',
        'rental' => 'Coche de alquiler',
        'public_transport' => 'Transporte público',
        'bicycle' => 'Bicicleta',
        'foot' => 'A pie',
        'other' => 'Otro',
    ],
    'trip_kind' => [
        'business' => 'Profesional',
        'commute' => 'Domicilio–trabajo',
        'private' => 'Privado',
    ],
    'tour_leg_purpose' => 'Trayecto a :title',
    'tour_return_purpose' => 'Viaje de vuelta',
    'driving_time' => [
        'legend' => 'Tiempos de conducción y descanso',
        'hint' => 'Para vehículos sujetos a las normas de tiempos de conducción: segundo conductor en conducción en equipo y travesías en las que el vehículo viaja en ferry o tren.',
        'co_driver' => 'Segundo conductor (conducción en equipo)',
        'co_driver_none' => '— conduce solo —',
        'ferry_or_train' => 'Ferry/tren: el vehículo viaja embarcado, sin tiempo de conducción',
    ],
];
