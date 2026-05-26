<?php
/* Merges translations from one or more PHP map files into lang/en.json,
 * preserving alphabetical order. Then mirrors en.json → fr.json + it.json.
 *
 * Map file format:
 *   <?php return [ 'German key' => 'English text', ... ];
 *
 * Usage: php scripts/translations-merge-en.php map1.php [map2.php ...]
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/..';

if ($argc < 2) {
    fwrite(STDERR, "usage: php scripts/translations-merge-en.php <map.php> [...]\n");
    exit(2);
}

$enFile = ROOT . '/lang/en.json';
$en = json_decode((string) file_get_contents($enFile), true, 512, JSON_THROW_ON_ERROR);

$added = 0;
$updated = 0;
$skipped = 0;
foreach (array_slice($argv, 1) as $mapPath) {
    if (! is_file($mapPath)) {
        fwrite(STDERR, "skip: $mapPath not found\n");
        continue;
    }
    $map = require $mapPath;
    if (! is_array($map)) {
        fwrite(STDERR, "skip: $mapPath did not return array\n");
        continue;
    }
    foreach ($map as $k => $v) {
        if (! is_string($k) || ! is_string($v)) {
            $skipped++;
            continue;
        }
        if (! array_key_exists($k, $en)) {
            $en[$k] = $v;
            $added++;
        } elseif ($en[$k] !== $v) {
            $en[$k] = $v;
            $updated++;
        }
    }
}

// Preserve existing file order; only newly added keys are appended (sorted).
$originalKeys = array_keys(json_decode((string) file_get_contents($enFile), true));
$ordered = [];
foreach ($originalKeys as $k) {
    if (array_key_exists($k, $en)) $ordered[$k] = $en[$k];
}
$newKeys = array_diff(array_keys($en), $originalKeys);
sort($newKeys, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($newKeys as $k) $ordered[$k] = $en[$k];
$en = $ordered;

file_put_contents(
    $enFile,
    json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
);

// Mirror
copy($enFile, ROOT . '/lang/fr.json');
copy($enFile, ROOT . '/lang/it.json');

echo "added=$added updated=$updated skipped=$skipped total=" . count($en) . "\n";
