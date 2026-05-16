<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : webpush.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),
    'ttl' => 4 * 7 * 24 * 60 * 60, // 4 Wochen
];
