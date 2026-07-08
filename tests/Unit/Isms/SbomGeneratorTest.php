<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SbomGeneratorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Isms;

use App\Services\Isms\SbomGenerator;
use Tests\TestCase;

/**
 * Unit-Test der SBOM-Kernlogik mit Fixture-Lockfiles (injizierte Inhalte,
 * keine echten Dateien): CycloneDX-1.5-Gerüst, purl-Bildung, Lizenz-,
 * Hash- und dev-Kennzeichnung für Composer und NPM.
 */
class SbomGeneratorTest extends TestCase {
    private const COMPOSER_LOCK = <<<'JSON'
    {
        "packages": [
            {
                "name": "acme/runtime-lib",
                "version": "v1.2.3",
                "license": ["MIT"],
                "dist": {"type": "zip", "url": "https://example.test", "reference": "abc", "shasum": "0123456789abcdef0123456789abcdef01234567"}
            }
        ],
        "packages-dev": [
            {
                "name": "acme/dev-tool",
                "version": "2.0.0",
                "license": ["AGPL-3.0-or-later"]
            }
        ]
    }
    JSON;

    private const PACKAGE_LOCK = <<<'JSON'
    {
        "lockfileVersion": 3,
        "packages": {
            "": {"name": "workdiary"},
            "node_modules/@acme/widget": {
                "version": "3.1.0",
                "license": "MIT",
                "integrity": "sha512-9jielHzVPqMlO9zQ6K1WtdhSHVX5OZfq5ZDUSVZfY10VnXRoRBx8nOadWjsG1xev/kz4EPaNN9eDFWtZSvJHxA=="
            },
            "node_modules/@acme/dev-only": {
                "version": "0.9.0",
                "dev": true,
                "license": "ISC"
            }
        }
    }
    JSON;

    public function test_generates_valid_cyclonedx_skeleton(): void {
        $document = $this->generate();

        $this->assertSame('CycloneDX', $document['bomFormat']);
        $this->assertSame('1.6', $document['specVersion']);
        $this->assertSame(1, $document['version']);
        $this->assertStringStartsWith('urn:uuid:', $document['serialNumber']);
        $this->assertArrayHasKey('timestamp', $document['metadata']);
        $this->assertSame('application', $document['metadata']['component']['type']);
        $this->assertSame('WorkDiary', $document['metadata']['component']['name']);

        // Root-Dependency-Eintrag deckt alle Komponenten ab.
        $refs = array_column($document['components'], 'bom-ref');
        $this->assertSame($refs, $document['dependencies'][0]['dependsOn']);
    }

    public function test_composer_packages_become_library_components_with_purl_license_and_dev_flag(): void {
        $components = collect($this->generate()['components']);

        $lib = $components->firstWhere('purl', 'pkg:composer/acme/runtime-lib@1.2.3');
        $this->assertNotNull($lib, 'composer-Paket fehlt (v-Präfix muss entfernt sein)');
        $this->assertSame('library', $lib['type']);
        $this->assertSame('required', $lib['scope']);
        $this->assertSame('MIT', $lib['licenses'][0]['license']['name']);
        $this->assertSame('SHA-1', $lib['hashes'][0]['alg']);
        $this->assertContains(['name' => 'workdiary:dependency.dev', 'value' => 'false'], $lib['properties']);

        $dev = $components->firstWhere('purl', 'pkg:composer/acme/dev-tool@2.0.0');
        $this->assertNotNull($dev);
        $this->assertSame('optional', $dev['scope']);
        $this->assertContains(['name' => 'workdiary:dependency.dev', 'value' => 'true'], $dev['properties']);
    }

    public function test_npm_packages_become_library_components_with_encoded_purl_and_hash(): void {
        $components = collect($this->generate()['components']);

        $widget = $components->firstWhere('purl', 'pkg:npm/%40acme/widget@3.1.0');
        $this->assertNotNull($widget, 'npm-Paket fehlt (Scope muss %40-kodiert sein)');
        $this->assertSame('library', $widget['type']);
        $this->assertSame('@acme/widget', $widget['name']);
        $this->assertSame('SHA-512', $widget['hashes'][0]['alg']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $widget['hashes'][0]['content']);

        $devOnly = $components->firstWhere('purl', 'pkg:npm/%40acme/dev-only@0.9.0');
        $this->assertNotNull($devOnly);
        $this->assertSame('optional', $devOnly['scope']);
        $this->assertContains(['name' => 'workdiary:dependency.dev', 'value' => 'true'], $devOnly['properties']);
    }

    public function test_runtime_and_module_components_are_included(): void {
        $document = $this->generate(modules: ['module.isms' => 'ISMS']);
        $components = collect($document['components']);

        $php = $components->firstWhere('name', 'php');
        $this->assertNotNull($php);
        $this->assertSame('platform', $php['type']);
        $this->assertSame(PHP_VERSION, $php['version']);

        $laravel = $components->firstWhere('name', 'laravel/framework');
        $this->assertNotNull($laravel);
        $this->assertSame('framework', $laravel['type']);

        $module = $components->firstWhere('bom-ref', 'module:module.isms');
        $this->assertNotNull($module);
        $this->assertSame('application', $module['type']);
        $this->assertSame((string) config('app.version', '0.1.0-dev'), $module['version']);
    }

    public function test_plugins_without_version_fall_back_to_bundled(): void {
        $document = $this->generate(plugins: [
            ['id' => 'demo', 'name' => 'Demo-Plugin', 'version' => ''],
            ['id' => 'versioned', 'name' => 'Versioniert', 'version' => '1.4.0'],
        ]);
        $components = collect($document['components']);

        $demo = $components->firstWhere('name', 'workdiary-plugin:demo');
        $this->assertNotNull($demo);
        $this->assertSame('bundled', $demo['version']);

        $versioned = $components->firstWhere('name', 'workdiary-plugin:versioned');
        $this->assertNotNull($versioned);
        $this->assertSame('1.4.0', $versioned['version']);
    }

    /**
     * @param  list<array{id: string, name: string, version: string}>  $plugins
     * @param  array<string, string>  $modules
     * @return array<string, mixed>
     */
    private function generate(array $plugins = [], array $modules = []): array {
        /** @var SbomGenerator $generator */
        $generator = app(SbomGenerator::class);

        return $generator->generate(self::COMPOSER_LOCK, self::PACKAGE_LOCK, $plugins, $modules);
    }
}
