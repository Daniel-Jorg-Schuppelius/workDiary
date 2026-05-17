<?php

return [
    'csv' => [
        'unreadable' => 'File is not readable.',
        'header_missing' => 'Header row missing or unreadable: :error',
        'name_column_missing' => 'Required column "Name" not found.',
    ],
    'routing' => [
        'nominatim_missing_coords' => 'Nominatim response is missing coordinates.',
        'nominatim_http' => 'Nominatim returned HTTP :status.',
    ],
    'upload' => [
        'too_large' => 'File is too large (max. :max KB).',
        'type_not_allowed' => 'File type not allowed.',
    ],
];
