<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : generate-sbom.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Erzeugt eine kombinierte CycloneDX-1.5-SBOM (Software Bill of Materials) aus
 * composer.lock und package-lock.json — Feature 051, MVP-098.
 *
 * Bewusst selbsttragend (keine zusätzliche Abhängigkeit, kein Netzwerkzugriff)
 * und deterministisch: Komponenten sind nach PURL sortiert, ohne Zeitstempel/
 * Zufalls-Seriennummer. Damit liefert derselbe Lock-Stand denselben Inhalt und
 * eignet sich als reproduzierbares Release-Artefakt sowie als Eingabe für
 * Advisory-Abgleiche.
 *
 * Aufruf: php scripts/generate-sbom.php [zieldatei]
 *   Standard-Ziel: storage/app/sbom.cdx.json
 */

$root = dirname(__DIR__);
$target = $argv[1] ?? $root . '/storage/app/sbom.cdx.json';

/** @return array<int, array{type: string, name: string, version: string, purl: string}> */
function composerComponents(string $lockPath): array {
    if (!is_file($lockPath)) {
        return [];
    }
    /** @var array{packages?: list<array{name?: string, version?: string}>, 'packages-dev'?: list<array{name?: string, version?: string}>} $lock */
    $lock = json_decode((string) file_get_contents($lockPath), true);
    $out = [];
    foreach (['packages', 'packages-dev'] as $section) {
        foreach ($lock[$section] ?? [] as $pkg) {
            $name = (string) ($pkg['name'] ?? '');
            $version = ltrim((string) ($pkg['version'] ?? ''), 'v');
            if ($name === '' || $version === '') {
                continue;
            }
            $out[] = [
                'type' => 'library',
                'name' => $name,
                'version' => $version,
                'purl' => 'pkg:composer/' . $name . '@' . $version,
            ];
        }
    }

    return $out;
}

/** @return array<int, array{type: string, name: string, version: string, purl: string}> */
function npmComponents(string $lockPath): array {
    if (!is_file($lockPath)) {
        return [];
    }
    /** @var array{packages?: array<string, array{version?: string}>} $lock */
    $lock = json_decode((string) file_get_contents($lockPath), true);
    $out = [];
    foreach ($lock['packages'] ?? [] as $path => $pkg) {
        if ($path === '' || !str_contains($path, 'node_modules/')) {
            continue; // Wurzelprojekt / Sonderpfade überspringen
        }
        $name = substr($path, strrpos($path, 'node_modules/') + strlen('node_modules/'));
        $version = (string) ($pkg['version'] ?? '');
        if ($name === '' || $version === '') {
            continue;
        }
        $out[] = [
            'type' => 'library',
            'name' => $name,
            'version' => $version,
            'purl' => 'pkg:npm/' . $name . '@' . $version,
        ];
    }

    return $out;
}

$components = array_merge(
    composerComponents($root . '/composer.lock'),
    npmComponents($root . '/package-lock.json'),
);

// Deterministische Reihenfolge + Deduplizierung über die PURL.
$byPurl = [];
foreach ($components as $component) {
    $byPurl[$component['purl']] = $component;
}
ksort($byPurl);
$components = array_values($byPurl);

$appName = 'workdiary';
$composerJson = $root . '/composer.json';
if (is_file($composerJson)) {
    /** @var array{name?: string} $meta */
    $meta = json_decode((string) file_get_contents($composerJson), true);
    $appName = (string) ($meta['name'] ?? $appName);
}

$bom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'version' => 1,
    'metadata' => [
        'component' => [
            'type' => 'application',
            'name' => $appName,
        ],
        'tools' => [
            ['name' => 'workdiary-sbom', 'vendor' => 'WorkDiary'],
        ],
    ],
    'components' => $components,
];

$dir = dirname($target);
if (!is_dir($dir)) {
    mkdir($dir, 0o775, true);
}

file_put_contents(
    $target,
    json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
);

fwrite(STDOUT, sprintf("SBOM geschrieben: %s (%d Komponenten)\n", $target, count($components)));
