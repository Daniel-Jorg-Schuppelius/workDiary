<?php

return [
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
    'ttl' => 4 * 7 * 24 * 60 * 60, // 4 Wochen
];
