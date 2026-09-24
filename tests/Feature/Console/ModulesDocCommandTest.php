<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesDocCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\{ModuleRegisterDocument, ModuleRegistry};
use CommonToolkit\Helper\FileSystem\File;
use Tests\TestCase;

/** `modules:doc` (MVP-873): schreibt das Modulregister, ohne Zielordner nichts. */
final class ModulesDocCommandTest extends TestCase {
    public function test_writes_the_register_to_the_given_path(): void {
        $path = storage_path('framework/testing/modul-register-' . getmypid() . '.md');
        File::delete($path);

        $this->artisan('modules:doc', ['--path' => $path])
            ->expectsOutputToContain('Modulregister geschrieben')
            ->assertExitCode(0);

        /** @var array<string, string> $helpRoutes */
        $helpRoutes = (array) config('help-topics.routes', []);
        $this->assertSame((new ModuleRegisterDocument(app(ModuleRegistry::class)))->render($helpRoutes), File::read($path));
        File::delete($path);
    }

    public function test_missing_target_folder_writes_nothing(): void {
        $path = storage_path('framework/testing/fehlt-' . getmypid() . '/modul-register.md');

        $this->artisan('modules:doc', ['--path' => $path])
            ->expectsOutputToContain('Zielordner nicht vorhanden')
            ->assertExitCode(0);

        $this->assertFalse(File::exists($path));
    }
}
