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

    // HTTP error pages (041-P0, MVP-053)
    'request_id' => 'Request ID',
    'report_problem' => 'Report a problem',
    '404' => [
        'title' => 'Page not found',
        'message' => 'The requested page does not exist or has been moved.',
    ],
    '403' => [
        'title' => 'Access denied',
        'message' => 'You do not have permission for this action. Please contact your administrator.',
    ],
    '419' => [
        'title' => 'Session expired',
        'message' => 'The page was open for too long. Please reload and try again.',
    ],
    '500' => [
        'title' => 'Internal error',
        'message' => 'An unexpected error occurred. Please try again later or report the problem including the request ID.',
    ],
];
