<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : check-junit-skips.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Skip-Zähler für die Finance-Suite (Vollscan 2026-08-23, D2/E3): Ohne
 * php-financial-formats skippen rund 58 Finance-Tests (DATEV-Hash-Kette,
 * Zahlungsabgleich, SEPA, Bankimport) komplett — unbemerkt, weil kein Gate
 * Skips zählte. Im CI-Job `tests-financial` ist das Paket installiert; dort
 * darf kein Test mehr übersprungen werden.
 *
 * Aufruf: php scripts/check-junit-skips.php <junit.xml> [--max=0]
 */

declare(strict_types=1);

$file = $argv[1] ?? null;
$max = 0;
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $max = (int) substr($arg, 6);
    }
}

if ($file === null || ! is_file($file)) {
    fwrite(STDERR, "JUnit-Datei fehlt: {$file}\n");
    exit(2);
}

$xml = @simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "JUnit-Datei nicht lesbar: {$file}\n");
    exit(2);
}

$skipped = [];
foreach ($xml->xpath('//testcase[skipped]') ?: [] as $case) {
    $skipped[] = (string) $case['class'] . '::' . (string) $case['name'];
}

$total = count($xml->xpath('//testcase') ?: []);
echo sprintf("JUnit: %d Tests, %d übersprungen (erlaubt: %d)\n", $total, count($skipped), $max);

if (count($skipped) > $max) {
    echo "Übersprungene Tests:\n  " . implode("\n  ", $skipped) . "\n";
    echo "Ohne php-financial-formats laufen diese Tests nie — Paket im Job installieren (COMPOSER_AUTH) oder Skip-Grund beheben.\n";
    exit(1);
}

exit(0);
