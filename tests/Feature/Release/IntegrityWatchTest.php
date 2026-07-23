<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityWatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Release;

use App\Console\Commands\Integrity\WatchCommand;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Support\Facades\File as FileFacade;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Realtime-Integritätswächter (Feature 097, MVP-453): deterministische Tests
 * für den überwachten Scope (vendor bewusst ausgeschlossen, Ausschlüsse und
 * Symlinks übersprungen) und den „ext-inotify fehlt"-Abbruch. Die Live-
 * inotify-Schleife (Änderung → Debounce → integrity:verify) ist per manuellem
 * Rauchtest belegt (persistRun wird erreicht); ein fork-basierter E2E-Test
 * bleibt bewusst weg, da er unter --parallel unzuverlässig wäre.
 */
class IntegrityWatchTest extends TestCase {
    private string $base = '';

    protected function setUp(): void {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/wd_watch_' . uniqid();
        FileFacade::makeDirectory($this->base . '/app/sub', 0775, true);
        FileFacade::makeDirectory($this->base . '/app/cache', 0775, true);
        FileFacade::makeDirectory($this->base . '/vendor/acme/pkg', 0775, true);
        file_put_contents($this->base . '/artisan', 'stub');
        file_put_contents($this->base . '/app/a.php', 'a');
        // Verzeichnis-Symlink innerhalb des Scope — darf nie betreten werden.
        @symlink($this->base . '/vendor', $this->base . '/app/linked');

        config()->set('integrity.base', $this->base);
        config()->set('integrity.paths', ['app']);
        config()->set('integrity.root_files', ['artisan', 'composer.json']);
        config()->set('integrity.exclude', ['app/cache']);
    }

    protected function tearDown(): void {
        FileFacade::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_watchable_directories_covers_scope_and_excludes_vendor(): void {
        $dirs = app(CodeIntegrityService::class)->watchableDirectories();

        // Scan-Wurzel (für root_files) + Scope-Verzeichnisse.
        $this->assertContains($this->base, $dirs);
        $this->assertContains($this->base . '/app', $dirs);
        $this->assertContains($this->base . '/app/sub', $dirs);

        // Ausschluss greift, Symlink-Verzeichnis wird nicht betreten,
        // vendor/ ist NIE Teil der Realtime-Überwachung.
        $this->assertNotContains($this->base . '/app/cache', $dirs);
        $this->assertNotContains($this->base . '/app/linked', $dirs);
        foreach ($dirs as $dir) {
            $this->assertStringNotContainsString('/vendor', $dir);
        }
    }

    public function test_is_excluded_path_matches_configured_prefixes(): void {
        $service = app(CodeIntegrityService::class);

        $this->assertTrue($service->isExcludedPath($this->base . '/app/cache'));
        $this->assertTrue($service->isExcludedPath($this->base . '/app/cache/deep/file.php'));
        $this->assertFalse($service->isExcludedPath($this->base . '/app/sub'));
    }

    public function test_root_watch_names_are_root_files_plus_scope_dirs(): void {
        $names = app(CodeIntegrityService::class)->rootWatchNames();

        $this->assertContains('artisan', $names);
        $this->assertContains('composer.json', $names);
        $this->assertContains('app', $names); // Scope-Verzeichnis am Repo-Root
    }

    public function test_command_aborts_cleanly_without_inotify(): void {
        // Anonyme Unterklasse simuliert eine fehlende Extension über den Seam.
        $command = new class extends WatchCommand {
            protected function hasInotify(): bool {
                return false;
            }
        };
        $command->setLaravel($this->app);

        $output = new BufferedOutput();
        $code = $command->run(new ArrayInput([]), $output);

        $this->assertSame(WatchCommand::FAILURE, $code);
        $this->assertStringContainsString('ext-inotify', $output->fetch());
    }
}
