<?php
/*
 * Hilfsskript: ergänzt den Trait `HasSqid` in den per CLI übergebenen Model-Dateien.
 *
 * Erwartete Struktur in den Models (auf das Repo zugeschnitten):
 *   - PSR-12 Header, namespace App\Models, dann use-Block
 *   - Imports aus `App\Models\Concerns\…` entweder als Einzel-Import oder als Gruppen-Import `{A, B}`
 *   - Klasse `class Xxx extends Model {` mit Inline-Trait-Liste ODER Trait-Pro-Zeile
 *
 * Vorgehen:
 *   1. `use HasSqid;` schon vorhanden → skip.
 *   2. Bestehenden Concerns-Import erweitern (Einzel-Import → Gruppen-Import; Gruppen-Import → HasSqid alphabetisch einfügen).
 *      Falls kein Concerns-Import vorhanden ist → neuen Import direkt nach dem ersten App\Models\…-Import oder vor dem ersten Nicht-App-Import einfügen.
 *   3. `use HasSqid;` als eigene Zeile direkt nach der `class … {`-Zeile einfügen.
 *      Falls die Klasse Inline-Traits hat (`use A, B;` ohne Factory-Docblock davor), wird `HasSqid` alphabetisch in dieser Liste ergänzt.
 *
 * Aufruf:  php scripts/add-has-sqid.php Customer.php DiaryEntry.php …
 *          (Dateinamen relativ zu app/Models)
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/add-has-sqid.php <ModelFile.php> [...]\n");
    exit(2);
}

$baseDir = dirname(__DIR__) . '/app/Models/';
$failures = [];
$skipped = [];
$modified = [];

for ($i = 1; $i < $argc; $i++) {
    $name = $argv[$i];
    $path = $baseDir . $name;

    if (!is_file($path)) {
        $failures[] = "$name: file not found";
        continue;
    }

    $src = file_get_contents($path);
    if ($src === false) {
        $failures[] = "$name: read failed";
        continue;
    }

    // 1. Schon vorhanden?
    if (preg_match('/\\b(use\\s+HasSqid;|HasSqid,|,\\s*HasSqid\\b|\\bHasSqid\\b\\s*[;,])/', $src)) {
        $skipped[] = "$name: already has HasSqid";
        continue;
    }

    // 2. Concerns-Import erweitern oder neu erstellen.
    $new = $src;
    $groupPattern = '/use\\s+App\\\\Models\\\\Concerns\\\\\\{([^}]+)\\};/';
    $singlePattern = '/use\\s+App\\\\Models\\\\Concerns\\\\([A-Za-z0-9_]+);/';

    if (preg_match($groupPattern, $new, $m)) {
        $items = array_map('trim', explode(',', $m[1]));
        if (!in_array('HasSqid', $items, true)) {
            $items[] = 'HasSqid';
            sort($items);
            $new = preg_replace($groupPattern, 'use App\\\\Models\\\\Concerns\\\\{' . implode(', ', $items) . '};', $new, 1);
        }
    } elseif (preg_match($singlePattern, $new, $m)) {
        $existing = $m[1];
        $items = [$existing, 'HasSqid'];
        sort($items);
        $new = preg_replace($singlePattern, 'use App\\\\Models\\\\Concerns\\\\{' . implode(', ', $items) . '};', $new, 1);
    } else {
        // Nach dem letzten App\Models\… Import einfügen, sonst nach namespace-Zeile.
        if (preg_match_all('/use\\s+App\\\\Models\\\\[^;]+;\\s*\\n/', $new, $allMatches, PREG_OFFSET_CAPTURE)) {
            $last = end($allMatches[0]);
            $pos = $last[1] + strlen($last[0]);
            $new = substr($new, 0, $pos) . "use App\\Models\\Concerns\\HasSqid;\n" . substr($new, $pos);
        } elseif (preg_match('/namespace\\s+App\\\\Models;\\s*\\n/', $new, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1] + strlen($m[0][0]);
            $new = substr($new, 0, $pos) . "\nuse App\\Models\\Concerns\\HasSqid;\n" . substr($new, $pos);
        } else {
            $failures[] = "$name: could not find an insertion point for Concerns import";
            continue;
        }
    }

    // 3. Trait in der Klasse einfügen.
    if (!preg_match('/class\\s+[A-Za-z_][A-Za-z0-9_]*\\s+extends\\s+[^\\s{]+(?:\\s+implements\\s+[^{]+)?\\s*\\{/', $new, $clsMatch, PREG_OFFSET_CAPTURE)) {
        $failures[] = "$name: could not find class declaration";
        continue;
    }

    $classDeclEnd = $clsMatch[0][1] + strlen($clsMatch[0][0]);

    // Suche nach Inline-Traits direkt unter Klassendeklaration (mehrere Traits in einer Zeile, ohne /** @use … */ davor).
    $afterClass = substr($new, $classDeclEnd);
    if (preg_match('/^\\s*\\/\\*\\*\\s*@use[^*]*\\*\\/\\s*\\n\\s*use\\s+([^;]+);\\s*\\n/', $afterClass, $um, PREG_OFFSET_CAPTURE)) {
        // Inline-Trait-Liste mit @use docblock → HasSqid alphabetisch ergänzen.
        $items = array_map('trim', explode(',', $um[1][0]));
        if (!in_array('HasSqid', $items, true)) {
            $items[] = 'HasSqid';
            sort($items);
            $absolutePos = $classDeclEnd + $um[0][1];
            $newUseLine = preg_replace('/use\\s+[^;]+;/', 'use ' . implode(', ', $items) . ';', $um[0][0], 1);
            $new = substr($new, 0, $absolutePos) . $newUseLine . substr($new, $absolutePos + strlen($um[0][0]));
        }
    } else {
        // Einfach `use HasSqid;` direkt nach `{` einfügen.
        $insert = "\n    use HasSqid;\n";
        $new = substr($new, 0, $classDeclEnd) . $insert . substr($new, $classDeclEnd);
    }

    if ($new === $src) {
        $skipped[] = "$name: no change";
        continue;
    }

    if (file_put_contents($path, $new) === false) {
        $failures[] = "$name: write failed";
        continue;
    }

    $modified[] = $name;
}

echo "Modified (" . count($modified) . "):\n";
foreach ($modified as $n) {
    echo "  + $n\n";
}
echo "Skipped (" . count($skipped) . "):\n";
foreach ($skipped as $n) {
    echo "  ~ $n\n";
}
echo "Failures (" . count($failures) . "):\n";
foreach ($failures as $n) {
    echo "  ! $n\n";
}

exit(count($failures) > 0 ? 1 : 0);
