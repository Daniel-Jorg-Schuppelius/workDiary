<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchedulerDocCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Scheduling\SchedulerRegisterDocument;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** `scheduler:doc` (Vollscan 2026-11-02, gap-03): schreibt das Scheduler-Register, ohne Zielordner nichts. */
final class SchedulerDocCommandTest extends TestCase {
    public function test_writes_the_register_to_the_given_path(): void {
        $path = storage_path('framework/testing/scheduler-register-' . getmypid() . '.md');
        File::delete($path);

        $this->artisan('scheduler:doc', ['--path' => $path])
            ->expectsOutputToContain('Scheduler-Register geschrieben')
            ->assertExitCode(0);

        $written = File::read($path);
        $this->assertSame($this->rendered(), $written);
        $this->assertStringContainsString('| `scheduler.watchdog` |', $written);
        $this->assertStringContainsString('## Belegung der Stunde', $written);
        File::delete($path);
    }

    public function test_rewrite_keeps_the_wiki_chronik_block(): void {
        $path = storage_path('framework/testing/scheduler-register-chronik-' . getmypid() . '.md');
        $rendered = $this->rendered();
        $chronik = "\n<!-- chronik:start -->\n\n## Chronik zu dieser Seite\n\n- 2026-11-02 · [gap-03](../chronik/audits/umsetzungsluecken-2026-11-02.md)\n\n<!-- chronik:end -->\n";
        File::write($path, $rendered . $chronik);

        $this->artisan('scheduler:doc', ['--path' => $path])->assertExitCode(0);

        $this->assertSame($rendered . $chronik, File::read($path));
        File::delete($path);
    }

    public function test_missing_target_folder_writes_nothing(): void {
        $path = storage_path('framework/testing/fehlt-' . getmypid() . '/scheduler-register.md');

        $this->artisan('scheduler:doc', ['--path' => $path])
            ->expectsOutputToContain('Zielordner nicht vorhanden')
            ->assertExitCode(0);

        $this->assertFalse(File::exists($path));
    }

    public function test_register_is_deterministic_and_lists_every_job(): void {
        $first = $this->rendered();

        $this->assertSame($first, $this->rendered());
        foreach (array_keys((array) config('scheduler.jobs', [])) as $key) {
            $this->assertStringContainsString('| `' . $key . '` |', $first);
        }
    }

    private function rendered(): string {
        /** @var array<string, array<string, mixed>> $jobs */
        $jobs = (array) config('scheduler.jobs', []);
        $labels = Lang::get('scheduler.job', [], 'de');

        return SchedulerRegisterDocument::fromConfig($jobs, is_array($labels) ? $labels : [])->render();
    }
}
