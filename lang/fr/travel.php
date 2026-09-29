<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : travel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'vehicle' => [
        'company' => 'Voiture de société',
        'private' => 'Voiture personnelle',
        'rental' => 'Voiture de location',
        'public_transport' => 'Transports en commun',
        'bicycle' => 'Vélo',
        'foot' => 'À pied',
        'other' => 'Autre',
    ],
    'trip_kind' => [
        'business' => 'Professionnel',
        'commute' => 'Domicile–travail',
        'private' => 'Privé',
    ],
    'tour_leg_purpose' => 'Trajet vers :title',
    'tour_return_purpose' => 'Trajet de retour',
    'driving_time' => [
        'legend' => 'Temps de conduite et de repos',
        'hint' => 'Pour les véhicules soumis aux règles de temps de conduite : second conducteur en équipage multiple et traversées où le véhicule voyage sur un ferry ou un train.',
        'co_driver' => 'Second conducteur (équipage multiple)',
        'co_driver_none' => '— seul à bord —',
        'ferry_or_train' => 'Ferry/train : le véhicule est transporté, pas de temps de conduite',
    ],
];
