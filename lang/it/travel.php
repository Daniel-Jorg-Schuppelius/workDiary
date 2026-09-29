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
        'company' => 'Auto aziendale',
        'private' => 'Auto privata',
        'rental' => 'Auto a noleggio',
        'public_transport' => 'Trasporto pubblico',
        'bicycle' => 'Bicicletta',
        'foot' => 'A piedi',
        'other' => 'Altro',
    ],
    'trip_kind' => [
        'business' => 'Aziendale',
        'commute' => 'Casa–lavoro',
        'private' => 'Privato',
    ],
    'tour_leg_purpose' => 'Tratta verso :title',
    'tour_return_purpose' => 'Viaggio di ritorno',
    'driving_time' => [
        'legend' => 'Tempi di guida e di riposo',
        'hint' => 'Per i veicoli soggetti alle regole sui tempi di guida: secondo conducente in multipresenza e traversate in cui il veicolo viaggia su traghetto o treno.',
        'co_driver' => 'Secondo conducente (multipresenza)',
        'co_driver_none' => '— da solo —',
        'ferry_or_train' => 'Traghetto/treno: il veicolo viaggia imbarcato, nessun tempo di guida',
    ],
];
