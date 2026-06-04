<?php

return [
    'type' => [
        'flextime' => 'Flexitime',
        'weekly' => 'Fixed weekly hours',
        'per_weekday' => 'Per weekday',
        'trust' => 'Trust-based working time',
    ],
    'type_hint' => [
        'flextime' => 'Uniform daily target on working days, with core and frame times.',
        'weekly' => 'A single weekly target, freely distributable across the week.',
        'per_weekday' => 'Individual hours or fixed start–end times per weekday.',
        'trust' => 'No target tracking – only actual attendance is recorded.',
    ],
];
