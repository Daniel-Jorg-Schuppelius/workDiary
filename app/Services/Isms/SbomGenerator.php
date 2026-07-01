<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SbomGenerator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Plugins\PluginManager;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;

/**
 * Release-SBOM für WorkDiary (Feature 044, Ebene 2 — produktbezogen):
 * erzeugt eine CycloneDX-1.5-JSON-Stückliste OHNE zusätzliche Pakete,
 * rein aus composer.lock, package-lock.json, Laufzeitumgebung sowie den
 * ausgelieferten WorkDiary-Modulen (config/plans.php-Labels) und Plugins.
 * Orientierung: BSI TR-03183-2 (Mindestinhalte); die SBOM ist bewusst
 * KEIN Schwachstellenbericht — Advisories/Betroffenheit laufen separat
 * (MVP 2).
 *
 * Kernlogik unit-testbar: Lockfile-Inhalte und Plugin-/Modul-Listen sind
 * als Parameter injizierbar; nur die Defaults greifen auf Dateisystem,
 * PluginManager und config zu.
 */
class SbomGenerator {
    public function __construct(private readonly PluginManager $plugins) {}

    /**
     * Baut das CycloneDX-1.5-Dokument als Array.
     *
     * @param  string|null  $composerLockJson  Inhalt von composer.lock (null ⇒ base_path)
     * @param  string|null  $packageLockJson  Inhalt von package-lock.json (null ⇒ base_path)
     * @param  list<array{id: string, name: string, version: string}>|null  $plugins  null ⇒ PluginManager
     * @param  array<string, string>|null  $modules  Modul-Code ⇒ Label (null ⇒ config plans.labels)
     * @return array<string, mixed>
     */
    public function generate(
        ?string $composerLockJson = null,
        ?string $packageLockJson = null,
        ?array $plugins = null,
        ?array $modules = null,
    ): array {
        $appVersion = (string) config('app.version', '0.1.0-dev');
        $gitHash = $this->resolveGitHash();

        $components = [];
        $components = array_merge($components, $this->runtimeComponents());
        $components = array_merge($components, $this->moduleComponents($modules ?? $this->defaultModules(), $appVersion));
        $components = array_merge($components, $this->pluginComponents($plugins ?? $this->defaultPlugins()));
        $components = array_merge($components, $this->composerComponents($composerLockJson ?? $this->readLockfile('composer.lock')));
        $components = array_merge($components, $this->npmComponents($packageLockJson ?? $this->readLockfile('package-lock.json')));

        $rootRef = 'pkg:generic/workdiary@' . $appVersion;
        $rootProperties = [['name' => 'workdiary:environment', 'value' => (string) app()->environment()]];
        if ($gitHash !== null) {
            $rootProperties[] = ['name' => 'workdiary:build.hash', 'value' => $gitHash];
        }

        return [
            'bomFormat' => 'CycloneDX',
            'specVersion' => '1.5',
            'serialNumber' => 'urn:uuid:' . Str::uuid()->toString(),
            'version' => 1,
            'metadata' => [
                'timestamp' => now()->toIso8601String(),
                'tools' => [
                    ['vendor' => 'WorkDiary', 'name' => 'sbom:generate', 'version' => $appVersion],
                ],
                'component' => [
                    'bom-ref' => $rootRef,
                    'type' => 'application',
                    'name' => 'WorkDiary',
                    'version' => $appVersion,
                    'properties' => $rootProperties,
                ],
            ],
            'components' => $components,
            // Flache Abhängigkeitsbeziehung: das Primärprodukt hängt von allen
            // gelisteten Komponenten ab (vollständiger Graph ist MVP-2+).
            'dependencies' => [
                [
                    'ref' => $rootRef,
                    'dependsOn' => array_map(
                        static fn(array $c): string => (string) $c['bom-ref'],
                        $components,
                    ),
                ],
            ],
        ];
    }

    /**
     * Dokument als formatiertes JSON (stabil für Hash-Bildung).
     *
     * @param  array<string, mixed>|null  $document
     */
    public function toJson(?array $document = null): string {
        $document ??= $this->generate();

        return JsonHelper::encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Dateiname je Release-Stand, z. B. workdiary-1.2.3-20260611-120000.cdx.json. */
    public function fileName(): string {
        return sprintf(
            'workdiary-%s-%s.cdx.json',
            (string) config('app.version', '0.1.0-dev'),
            now()->format('Ymd-His'),
        );
    }

    /** Stabiler Alias für „letzte SBOM" (Admin-Download). */
    public static function latestAlias(): string {
        return 'workdiary-latest.cdx.json';
    }

    /**
     * Kurzer Git-Commit-Hash, robust gegen fehlendes git/Repo (Tests, Deploys
     * aus Tarballs): liefert dann null.
     */
    public function resolveGitHash(): ?string {
        try {
            $out = @exec('git -C ' . escapeshellarg(base_path()) . ' rev-parse --short HEAD 2>/dev/null');
        } catch (\Throwable) {
            return null;
        }

        $hash = is_string($out) ? trim($out) : '';

        return preg_match('/^[0-9a-f]{6,40}$/', $hash) === 1 ? $hash : null;
    }

    /**
     * Laufzeitumgebung: PHP, Laravel-Framework und DB-Treiber.
     *
     * @return list<array<string, mixed>>
     */
    private function runtimeComponents(): array {
        $dbConnection = (string) config('database.default', '');
        $dbDriver = (string) config("database.connections.{$dbConnection}.driver", $dbConnection);

        return [
            [
                'bom-ref' => 'runtime:php@' . PHP_VERSION,
                'type' => 'platform',
                'name' => 'php',
                'version' => PHP_VERSION,
            ],
            [
                'bom-ref' => 'runtime:laravel@' . Application::VERSION,
                'type' => 'framework',
                'name' => 'laravel/framework',
                'version' => Application::VERSION,
                'purl' => 'pkg:composer/laravel/framework@' . Application::VERSION,
            ],
            [
                'bom-ref' => 'runtime:database-driver:' . ($dbDriver !== '' ? $dbDriver : 'unknown'),
                'type' => 'platform',
                'name' => 'database-driver:' . ($dbDriver !== '' ? $dbDriver : 'unknown'),
                'version' => 'configured',
            ],
        ];
    }

    /**
     * Ausgelieferte WorkDiary-Module (config/plans.php-Labels): Module sind
     * Bestandteil JEDES Releases — die org-spezifische Aktivierung (Lizenz)
     * ist eine Betriebs-, keine Release-Eigenschaft.
     *
     * @param  array<string, string>  $modules
     * @return list<array<string, mixed>>
     */
    private function moduleComponents(array $modules, string $appVersion): array {
        $components = [];
        foreach ($modules as $code => $label) {
            $components[] = [
                'bom-ref' => 'module:' . $code,
                'type' => 'application',
                'name' => 'workdiary-module:' . $code,
                'description' => (string) $label,
                'version' => $appVersion,
            ];
        }

        return $components;
    }

    /**
     * Registrierte Plugins (Implementierungsstand; Version aus dem
     * Plugin-Contract, sonst 'bundled').
     *
     * @param  list<array{id: string, name: string, version: string}>  $plugins
     * @return list<array<string, mixed>>
     */
    private function pluginComponents(array $plugins): array {
        $components = [];
        foreach ($plugins as $plugin) {
            $version = $plugin['version'] !== '' ? $plugin['version'] : 'bundled';
            $components[] = [
                'bom-ref' => 'plugin:' . $plugin['id'] . '@' . $version,
                'type' => 'application',
                'name' => 'workdiary-plugin:' . $plugin['id'],
                'description' => $plugin['name'],
                'version' => $version,
            ];
        }

        return $components;
    }

    /**
     * Composer-Pakete aus composer.lock (packages + packages-dev) mit purl,
     * Lizenz(en), dist-Hash und dev-Kennzeichnung (scope optional + Property).
     *
     * @return list<array<string, mixed>>
     */
    private function composerComponents(string $lockJson): array {
        $lock = json_decode($lockJson, true);
        if (! is_array($lock)) {
            return [];
        }

        $components = [];
        foreach (['packages' => false, 'packages-dev' => true] as $section => $dev) {
            $packages = $lock[$section] ?? [];
            if (! is_array($packages)) {
                continue;
            }
            foreach ($packages as $package) {
                if (! is_array($package) || ! isset($package['name'], $package['version'])) {
                    continue;
                }
                $name = (string) $package['name'];
                $version = ltrim((string) $package['version'], 'v');
                $purl = 'pkg:composer/' . $name . '@' . $version;

                $component = [
                    'bom-ref' => $purl,
                    'type' => 'library',
                    'name' => $name,
                    'version' => $version,
                    'purl' => $purl,
                    'scope' => $dev ? 'optional' : 'required',
                    'properties' => [
                        ['name' => 'workdiary:dependency.dev', 'value' => $dev ? 'true' : 'false'],
                        ['name' => 'workdiary:package.manager', 'value' => 'composer'],
                    ],
                ];

                $licenses = $this->licenseEntries($package['license'] ?? null);
                if ($licenses !== []) {
                    $component['licenses'] = $licenses;
                }

                $shasum = $package['dist']['shasum'] ?? null;
                if (is_string($shasum) && $shasum !== '') {
                    $component['hashes'] = [['alg' => 'SHA-1', 'content' => $shasum]];
                }

                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * NPM-Pakete aus package-lock.json (lockfileVersion 3: "packages"-Map mit
     * node_modules/-Schlüsseln) mit purl, Lizenz, Integrity-Hash und
     * dev-Kennzeichnung.
     *
     * @return list<array<string, mixed>>
     */
    private function npmComponents(string $lockJson): array {
        $lock = json_decode($lockJson, true);
        if (! is_array($lock) || ! is_array($lock['packages'] ?? null)) {
            return [];
        }

        $components = [];
        $seen = [];
        foreach ($lock['packages'] as $path => $info) {
            if (! is_string($path) || ! str_contains($path, 'node_modules/') || ! is_array($info)) {
                continue; // ""-Root-Eintrag und Workspaces überspringen.
            }
            $version = $info['version'] ?? null;
            if (! is_string($version) || $version === '') {
                continue;
            }

            // Name = Pfadteil nach dem LETZTEN node_modules/ (verschachtelte Pakete).
            $name = Str::afterLast($path, 'node_modules/');
            if ($name === '') {
                continue;
            }

            $purl = 'pkg:npm/' . str_replace('@', '%40', $name) . '@' . $version;
            if (isset($seen[$purl])) {
                continue; // identische Version mehrfach im Baum — einmal listen.
            }
            $seen[$purl] = true;

            $dev = ($info['dev'] ?? false) === true;
            $component = [
                'bom-ref' => $purl,
                'type' => 'library',
                'name' => $name,
                'version' => $version,
                'purl' => $purl,
                'scope' => $dev ? 'optional' : 'required',
                'properties' => [
                    ['name' => 'workdiary:dependency.dev', 'value' => $dev ? 'true' : 'false'],
                    ['name' => 'workdiary:package.manager', 'value' => 'npm'],
                ],
            ];

            $licenses = $this->licenseEntries($info['license'] ?? null);
            if ($licenses !== []) {
                $component['licenses'] = $licenses;
            }

            $hash = $this->integrityHash($info['integrity'] ?? null);
            if ($hash !== null) {
                $component['hashes'] = [$hash];
            }

            $components[] = $component;
        }

        return $components;
    }

    /**
     * Lizenzangaben (string|list<string>) als CycloneDX licenses-Liste —
     * bewusst als 'name' (kein SPDX-Id-Anspruch, Lockfiles sind nicht
     * verlässlich SPDX-konform).
     *
     * @return list<array{license: array{name: string}}>
     */
    private function licenseEntries(mixed $raw): array {
        $list = is_string($raw) ? [$raw] : (is_array($raw) ? $raw : []);

        $entries = [];
        foreach ($list as $license) {
            if (is_string($license) && $license !== '') {
                $entries[] = ['license' => ['name' => $license]];
            }
        }

        return $entries;
    }

    /**
     * npm-Integrity (SRI, base64) → CycloneDX-Hash (hex). Unbekannte
     * Algorithmen/kaputte Werte werden still übersprungen.
     *
     * @return array{alg: string, content: string}|null
     */
    private function integrityHash(mixed $integrity): ?array {
        if (! is_string($integrity) || ! str_contains($integrity, '-')) {
            return null;
        }

        [$alg, $b64] = explode('-', $integrity, 2);
        $cdxAlg = match ($alg) {
            'sha256' => 'SHA-256',
            'sha384' => 'SHA-384',
            'sha512' => 'SHA-512',
            default => null,
        };
        if ($cdxAlg === null) {
            return null;
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        return ['alg' => $cdxAlg, 'content' => bin2hex($binary)];
    }

    private function readLockfile(string $file): string {
        $path = base_path($file);
        $content = is_file($path) ? file_get_contents($path) : false;

        return $content === false ? '{}' : $content;
    }

    /** @return array<string, string> */
    private function defaultModules(): array {
        /** @var array<string, string> $labels */
        $labels = (array) config('plans.labels', []);

        return $labels;
    }

    /** @return list<array{id: string, name: string, version: string}> */
    private function defaultPlugins(): array {
        $plugins = [];
        foreach ($this->plugins->all() as $plugin) {
            $version = (string) $this->plugins->invoke($plugin, fn(): string => $plugin->version());
            $plugins[] = [
                'id' => $plugin->id(),
                'name' => $plugin->name(),
                'version' => $version,
            ];
        }

        return $plugins;
    }
}
