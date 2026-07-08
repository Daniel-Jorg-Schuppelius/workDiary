<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SbomCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Services\Isms\SbomGenerator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature-Test des sbom:generate-Commands: Lauf gegen die ECHTEN
 * Lockfiles des Repos (composer.lock/package-lock.json), Ablage unter
 * storage/app/sbom (gefakter local-Disk) inkl. Alias und SHA-256-Ausgabe.
 */
class SbomCommandTest extends TestCase {
    public function test_command_generates_sbom_file_and_alias(): void {
        Storage::fake('local');

        $this->artisan('sbom:generate')
            ->expectsOutputToContain('SHA-256:')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('local')->exists('sbom/' . SbomGenerator::latestAlias()));

        $json = (string) Storage::disk('local')->get('sbom/' . SbomGenerator::latestAlias());
        $document = json_decode($json, true);

        $this->assertIsArray($document);
        $this->assertSame('CycloneDX', $document['bomFormat']);
        $this->assertSame('1.6', $document['specVersion']);
        $this->assertNotEmpty($document['components']);

        // Echte Lockfiles: laravel/framework muss als Composer-Komponente enthalten sein.
        $purls = array_column($document['components'], 'purl');
        $this->assertNotEmpty(array_filter(
            $purls,
            static fn(?string $purl): bool => is_string($purl) && str_starts_with($purl, 'pkg:composer/laravel/framework@'),
        ));
    }

    public function test_command_print_option_writes_to_stdout_without_file(): void {
        Storage::fake('local');

        $this->artisan('sbom:generate --print')
            ->expectsOutputToContain('"bomFormat": "CycloneDX"')
            ->assertExitCode(0);

        $this->assertSame([], Storage::disk('local')->allFiles('sbom'));
    }

    public function test_command_output_option_writes_to_custom_path(): void {
        $target = sys_get_temp_dir() . '/workdiary-sbom-test/custom.cdx.json';
        @unlink($target);

        $this->artisan('sbom:generate --output=' . $target)
            ->assertExitCode(0);

        $this->assertFileExists($target);
        $document = json_decode((string) file_get_contents($target), true);
        $this->assertIsArray($document);
        $this->assertSame('CycloneDX', $document['bomFormat']);

        @unlink($target);
    }
}
