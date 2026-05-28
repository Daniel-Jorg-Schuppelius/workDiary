<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : migrate-selects-to-sqid.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Phase-3b-Refactor: ersetzt in Blade-Templates int-IDs in
 * Selects / Inputs / @selected-Vergleichen durch Sqids.
 *
 * Aufruf:
 *   php scripts/migrate-selects-to-sqid.php           # Dry-Run
 *   php scripts/migrate-selects-to-sqid.php --apply   # Schreiben
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$apply = in_array('--apply', $argv, true);

/** Var-Name → Modell-Kurzname (HasSqid). NULL = nicht anfassen. */
$varToModel = [
    'u' => 'User',
    'user' => 'User',
    'reportUser' => 'User',
    'targetUser' => 'User',
    'customer' => 'Customer',
    'tag' => 'Tag',
    'entry' => 'DiaryEntry',
    'd' => 'DiaryEntry',
    'entryType' => 'EntryType',
    'type' => 'EntryType',
    'site' => 'Site',
    'building' => 'Building',
    'b' => 'Building',
    'floor' => 'Floor',
    'room' => 'Room',
    'r' => 'Room',
    'asset' => 'Asset',
    'vehicle' => 'Vehicle',
    'v' => 'Vehicle',
    'task' => 'Task',
    'ms' => 'Milestone',
    'milestone' => 'Milestone',
    'material' => 'Material',
    'tour' => 'Tour',
    'cat' => 'EventCategory',
    'category' => 'EventCategory',
    'sw' => 'Software',
    'software' => 'Software',
    'prev' => 'SickLeave',
    'sickLeave' => 'SickLeave',
    'expense' => 'Expense',
    'profile' => 'CleaningProfile',
    'st' => 'ShiftType',
    'q' => 'Qualification',
    'tpl' => 'InvoiceTemplate',
    'organization' => 'Organization',
    'orgItem' => 'Organization',
    '_orgItem' => 'Organization',
    'sourceClassification' => 'Classification',
    'classification' => 'Classification',
    'pt' => 'Task',
    'rule' => 'RecurrenceRule',
    'event' => 'Event',
    'trip' => 'PerDiemTrip',
    'tl' => 'TravelLog',
    'openAttendance' => 'Attendance',
    'shift' => 'ScheduledShift',
    // skip:
    'project' => null,
    'p' => null,
    'c' => null,
    's' => null,
    't' => null,
    'a' => null,
    'm' => null,
    'role' => null,
    'group' => null,
    'plugin' => null,
    'session' => null,
    'token' => null,
    'legacyUser' => null,
    'val' => null,
    'log' => null,
    'subject' => null,
    'opt' => null,
];

$skipPathParts = [
    '/legacy/',
    '/admin/plugins/',
    '/admin/privacy/',
    '/admin/access/',
    '/profile/api-tokens',
    '/Plugins/RemoteSupport/',
    '/Plugins/Toggl/',
];

$model = static function (string $var) use ($varToModel): ?string {
    return array_key_exists($var, $varToModel) ? $varToModel[$var] : null;
};

/** @var array<string, int> $changesPerFile */
$changesPerFile = [];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views', RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    /** @var SplFileInfo $f */
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
        continue;
    }
    $files[] = $f->getPathname();
}

foreach ($files as $file) {
    $rel = substr($file, strlen($root) + 1);
    foreach ($skipPathParts as $part) {
        if (str_contains('/' . $rel, $part)) {
            continue 2;
        }
    }

    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }
    $changes = 0;

    // 1) value="{{ $X->id }}"
    $content = preg_replace_callback(
        '/value="\{\{\s*\$(\w+)->id\s*\}\}"/',
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[1]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return 'value="{{ $' . $m[1] . '->sqid }}"';
        },
        $content,
    ) ?? $content;

    // 2) @selected((int) old('FIELD'[, DEFAULT]) === (int) $X->id)
    $content = preg_replace_callback(
        "/@selected\(\(int\)\s*old\(('[^']+')(,\s*\\\$\w+\??->\w+)?\)\s*={2,3}\s*\(int\)\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[3]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            $default = '';
            if (! empty($m[2])) {
                $expr = trim(substr($m[2], 1));
                $default = ', sqid(\\App\\Models\\' . $cls . '::class, ' . $expr . ')';
            }
            return '@selected((string) old(' . $m[1] . $default . ') === $' . $m[3] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 3) @selected(old('FIELD'[, DEFAULT]) == $X->id)
    $content = preg_replace_callback(
        "/@selected\(old\(('[^']+')(,\s*\\\$\w+\??->\w+)?\)\s*={2,3}\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[3]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            $default = '';
            if (! empty($m[2])) {
                $expr = trim(substr($m[2], 1));
                $default = ', sqid(\\App\\Models\\' . $cls . '::class, ' . $expr . ')';
            }
            return '@selected((string) old(' . $m[1] . $default . ') === $' . $m[3] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 4) @selected((int) ($filters['FIELD'] ?? 0) === (int) $X->id)
    $content = preg_replace_callback(
        "/@selected\(\(int\)\s*\(\\\$filters\[('[^']+')\]\s*\?\?\s*0\)\s*={2,3}\s*\(int\)\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected((string) ($filters[' . $m[1] . "] ?? '') === \$" . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 5) @selected((int) ($filters['FIELD'] ?? 0) === $X->id)
    $content = preg_replace_callback(
        "/@selected\(\(int\)\s*\(\\\$filters\[('[^']+')\]\s*\?\?\s*0\)\s*={2,3}\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected((string) ($filters[' . $m[1] . "] ?? '') === \$" . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 6) @selected($foo === (int) $X->id)
    $content = preg_replace_callback(
        "/@selected\(\\\$(\w+)\s*===\s*\(int\)\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected((string) $' . $m[1] . ' === $' . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 7) @selected($foo === $X->id)
    $content = preg_replace_callback(
        "/@selected\(\\\$(\w+)\s*===\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected((string) $' . $m[1] . ' === $' . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 8) @selected((string) request('X') === (string) $X->id)
    $content = preg_replace_callback(
        "/@selected\(\(string\)\s*request\(('[^']+')\)\s*={2,3}\s*\(string\)\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected(request(' . $m[1] . ') === $' . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 9) @selected(($filters['X'] ?? '') == $X->id)   -- request() variant
    $content = preg_replace_callback(
        "/@selected\(\(\\\$filters\[('[^']+')\]\s*\?\?\s*''\)\s*={2,3}\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $cls = $model($m[2]);
            if ($cls === null) {
                return $m[0];
            }
            $changes++;
            return '@selected(($filters[' . $m[1] . "] ?? '') === \$" . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 10) @selected((int) $u->id === (int) $user->id) — comparison between two model objects
    $content = preg_replace_callback(
        "/@selected\(\(int\)\s*\\\$(\w+)->id\s*={2,3}\s*\(int\)\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $clsA = $model($m[1]);
            $clsB = $model($m[2]);
            if ($clsA === null || $clsB === null) {
                return $m[0];
            }
            $changes++;
            return '@selected($' . $m[1] . '->sqid === $' . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    // 11) @selected($targetUser?->id === $u->id)
    $content = preg_replace_callback(
        "/@selected\(\\\$(\w+)\??->id\s*===\s*\\\$(\w+)->id\)/",
        static function (array $m) use ($model, &$changes): string {
            $clsA = $model($m[1]);
            $clsB = $model($m[2]);
            if ($clsA === null || $clsB === null) {
                return $m[0];
            }
            $changes++;
            return '@selected($' . $m[1] . '?->sqid === $' . $m[2] . '->sqid)';
        },
        $content,
    ) ?? $content;

    if ($changes > 0) {
        $changesPerFile[$rel] = $changes;
        if ($apply) {
            file_put_contents($file, $content);
        }
    }
}

ksort($changesPerFile);
echo ($apply ? 'APPLIED' : 'DRY-RUN') . ' — Touched files: ' . count($changesPerFile) . "\n";
foreach ($changesPerFile as $f => $n) {
    echo sprintf("  %4d  %s\n", $n, $f);
}
echo "Total replacements: " . array_sum($changesPerFile) . "\n";

if (! $apply) {
    echo "\nRun again with --apply to write changes.\n";
}
