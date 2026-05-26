<?php
/* Parses storage/reports/translations-coverage.md and groups missing JSON
 * keys by source file/module (first occurrence). Outputs a tsv:
 *   <module>\t<key>
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';
$report = (string) file_get_contents(ROOT . '/storage/reports/translations-coverage.md');

$blocks = preg_split('/^### `/m', $report);
array_shift($blocks); // drop header before first key

$groups = [];
foreach ($blocks as $block) {
    [$keyLine, $rest] = explode("`\n", $block, 2) + [null, ''];
    if ($keyLine === null) continue;
    $key = $keyLine;
    // Stop at section B
    if (str_contains($rest, '## B —')) {
        [$rest] = explode('## B —', $rest, 2);
    }
    // Skip dotted (already in PHP catalogs) — keep only plain
    if (preg_match('/^[a-z][a-z0-9_-]*(\.[a-zA-Z0-9_-]+)+$/', $key)) continue;

    // First occurrence file path
    if (preg_match('/^- ([^:]+):\d+/m', $rest, $m)) {
        $file = $m[1];
        // module heuristic
        $module = 'misc';
        if (preg_match('#^app/Http/Controllers/Admin/#', $file)) $module = 'admin';
        elseif (preg_match('#^resources/views/admin/#', $file)) $module = 'admin';
        elseif (preg_match('#^resources/views/reports/#', $file)) $module = 'reports';
        elseif (preg_match('#^app/Http/Controllers/Reporting/#', $file)) $module = 'reports';
        elseif (preg_match('#^app/Http/Controllers/Privacy|resources/views/admin/privacy#', $file)) $module = 'privacy';
        elseif (preg_match('#expense|spese|Expense#', $file)) $module = 'expense';
        elseif (preg_match('#diary#', $file)) $module = 'diary';
        elseif (preg_match('#timesheet|TimeEntry#', $file)) $module = 'timesheet';
        elseif (preg_match('#invoice|Invoice#', $file)) $module = 'invoice';
        elseif (preg_match('#protocol#i', $file)) $module = 'protocol';
        elseif (preg_match('#procedure#i', $file)) $module = 'procedure';
        elseif (preg_match('#open-issue|OpenIssue#', $file)) $module = 'openissue';
        elseif (preg_match('#tour|Tour|travel#', $file)) $module = 'travel';
        elseif (preg_match('#event|Event#', $file)) $module = 'event';
        elseif (preg_match('#duty|shift|emergency|Schedule#', $file)) $module = 'schedule';
        elseif (preg_match('#material|asset|Material|Asset#', $file)) $module = 'inventory';
        elseif (preg_match('#vacation|leave|sick|attendance#', $file)) $module = 'absence';
        elseif (preg_match('#auth|login|register#', $file)) $module = 'auth';
        elseif (preg_match('#dashboard|widget#', $file)) $module = 'dashboard';
        elseif (preg_match('#layouts/app#', $file)) $module = 'layout';
        elseif (preg_match('#components/#', $file)) $module = 'components';
        $groups[$module][$key] = $file;
    }
}

ksort($groups);
foreach ($groups as $mod => $keys) {
    foreach (array_keys($keys) as $k) {
        echo $mod . "\t" . $k . "\n";
    }
}
fwrite(STDERR, "\nGroup sizes:\n");
foreach ($groups as $mod => $keys) fwrite(STDERR, sprintf("  %-12s %4d\n", $mod, count($keys)));
fwrite(STDERR, "  total       " . array_sum(array_map('count', $groups)) . "\n");
