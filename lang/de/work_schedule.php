<?php

return [
    'type' => [
        'flextime' => 'Gleitzeit',
        'weekly' => 'Feste Wochenarbeitszeit',
        'per_weekday' => 'Wochentagsweise',
        'trust' => 'Vertrauensarbeitszeit',
    ],
    'type_hint' => [
        'flextime' => 'Einheitliches Tagessoll an Arbeitstagen, mit Kern- und Rahmenzeit.',
        'weekly' => 'Nur ein Wochensoll, frei über die Woche verteilbar.',
        'per_weekday' => 'Pro Wochentag individuelle Stunden oder feste Von–bis-Zeiten.',
        'trust' => 'Keine Soll-Erfassung – es wird nur die Ist-Anwesenheit erfasst.',
    ],
];
