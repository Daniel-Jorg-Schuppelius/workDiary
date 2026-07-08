<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : merge-sbom.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Hierarchischer CycloneDX-1.6-Merge (Feature 044e, AR §23): führt die von
 * cyclonedx-php-composer und @cyclonedx/cyclonedx-npm erzeugten Teil-SBOMs zu
 * EINER SBOM mit App-Root zusammen — Ersatz für das .NET-Tool `cyclonedx-cli
 * merge --hierarchical` (keine zusätzliche Runtime nötig). Deterministisch:
 * Komponenten/Kanten nach purl/ref sortiert, ohne Zeitstempel/Zufalls-Serial.
 *
 * Aufruf: php scripts/merge-sbom.php <composer-sbom> <npm-sbom> <ziel> [version]
 */

if ($argc < 4) {
    fwrite(STDERR, "Aufruf: php scripts/merge-sbom.php <composer-sbom> <npm-sbom> <ziel> [version]\n");
    exit(1);
}

[, $composerPath, $npmPath, $target] = $argv;
$appVersion = $argv[4] ?? 'dev';

/** @return array{components: list<array<string, mixed>>, dependencies: list<array<string, mixed>>, rootRef: ?string} */
function loadPart(string $path): array {
    if (! is_file($path)) {
        return ['components' => [], 'dependencies' => [], 'rootRef' => null];
    }
    /** @var array<string, mixed> $doc */
    $doc = (array) json_decode((string) file_get_contents($path), true);

    $rootRef = null;
    $rootComponent = $doc['metadata']['component'] ?? null;
    if (is_array($rootComponent)) {
        $rootRef = isset($rootComponent['bom-ref']) ? (string) $rootComponent['bom-ref'] : null;
    }

    /** @var list<array<string, mixed>> $components */
    $components = array_values((array) ($doc['components'] ?? []));
    /** @var list<array<string, mixed>> $dependencies */
    $dependencies = array_values((array) ($doc['dependencies'] ?? []));

    return ['components' => $components, 'dependencies' => $dependencies, 'rootRef' => $rootRef];
}

$composer = loadPart($composerPath);
$npm = loadPart($npmPath);

$components = [];
$seen = [];
foreach (array_merge($composer['components'], $npm['components']) as $component) {
    $key = (string) ($component['purl'] ?? $component['bom-ref'] ?? json_encode($component));
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $components[] = $component;
}
usort($components, static fn(array $a, array $b): int => strcmp((string) ($a['purl'] ?? $a['bom-ref'] ?? ''), (string) ($b['purl'] ?? $b['bom-ref'] ?? '')));

$rootRef = 'pkg:generic/workdiary@' . $appVersion;
$dependencies = [[
    'ref' => $rootRef,
    'dependsOn' => array_values(array_filter([$composer['rootRef'], $npm['rootRef']])),
]];
foreach (array_merge($composer['dependencies'], $npm['dependencies']) as $edge) {
    $dependencies[] = $edge;
}
usort($dependencies, static fn(array $a, array $b): int => strcmp((string) ($a['ref'] ?? ''), (string) ($b['ref'] ?? '')));

$document = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.6',
    'version' => 1,
    'metadata' => [
        'component' => [
            'bom-ref' => $rootRef,
            'type' => 'application',
            'name' => 'workdiary',
            'version' => $appVersion,
            'purl' => $rootRef,
        ],
        'tools' => [
            ['name' => 'cyclonedx-php-composer + cyclonedx-npm + merge-sbom.php'],
        ],
    ],
    'components' => $components,
    'dependencies' => $dependencies,
];

$dir = dirname($target);
if (! is_dir($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($target, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo sprintf(
    "SBOM (1.6, hierarchisch) geschrieben: %s — %d Komponenten, %d Abhängigkeitskanten\n",
    $target,
    count($components),
    count($dependencies),
);
