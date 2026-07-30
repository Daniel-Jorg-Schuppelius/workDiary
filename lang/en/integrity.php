<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : integrity.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Integrity secondary signals and lockdown (feature 097, MVP-447/448).
return [
    'anchor' => [
        'unavailable' => 'External integrity anchor not readable (backup target reachable?) — secondary signal skipped.',
        'root_mismatch' => 'External anchor differs: anchor root :remote, local :local.',
        'history_mismatch' => 'Check history differs from the external anchor — the local history may have been replaced.',
    ],
    'env' => [
        'missing' => '.env missing or unreadable (the baseline holds a fingerprint).',
        'values_changed' => '.env changed (same key set, different values).',
        'keys_changed' => '.env changed (key set differs: :before → :after keys).',
    ],
    'git' => [
        'head_mismatch' => 'Git HEAD :head does not match the baseline build :expected (WARN).',
        'dirty' => 'Git working tree not clean within the scan scope: :count path(s) — :paths (WARN).',
    ],
    'lockdown' => [
        'crisis_title' => 'Integrity lockdown: source code tampered with',
        'crisis_description' => 'A signed release baseline shows deviations across consecutive runs (:modified modified, :added added, :deleted deleted). The installation is in maintenance mode.',
    ],
];
