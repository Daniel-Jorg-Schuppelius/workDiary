<?php
/*
 * Created on   : Tue Aug 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MakePluginCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakePluginCommandTest extends TestCase {
    private const STUDLY = 'ZzMakeStub';

    protected function tearDown(): void {
        File::deleteDirectory(app_path('Plugins/' . self::STUDLY));
        File::deleteDirectory(base_path('tests/Feature/Plugins/' . self::STUDLY));

        parent::tearDown();
    }

    public function test_scaffold_is_generated_with_degraded_health_default(): void {
        $this->artisan('make:plugin', ['name' => self::STUDLY])->assertSuccessful();

        $pluginFile = app_path('Plugins/' . self::STUDLY . '/' . self::STUDLY . 'Plugin.php');
        $this->assertFileExists($pluginFile);
        $this->assertFileExists(app_path('Plugins/' . self::STUDLY . '/' . self::STUDLY . 'ServiceProvider.php'));
        $this->assertFileExists(app_path('Plugins/' . self::STUDLY . '/config.php'));
        $this->assertFileExists(base_path('tests/Feature/Plugins/' . self::STUDLY . '/' . self::STUDLY . 'PluginTest.php'));

        $stub = (string) file_get_contents($pluginFile);
        $this->assertStringContainsString("public const ID = 'zz-make-stub';", $stub);
        // Frisch generierte Plugins dürfen nie still "ok" melden — Konvention
        // wie PluginDefaults::healthCheck() (Review 2026-08, A15/W0g).
        $this->assertStringContainsString("code: 'not_implemented'", $stub);
        $this->assertStringContainsString('PluginHealth::degraded', $stub);
        $this->assertStringNotContainsString('fn(): bool => true', $stub);
    }

    public function test_refuses_to_overwrite_existing_plugin_directory(): void {
        File::ensureDirectoryExists(app_path('Plugins/' . self::STUDLY));

        $this->artisan('make:plugin', ['name' => self::STUDLY])->assertFailed();
    }
}
