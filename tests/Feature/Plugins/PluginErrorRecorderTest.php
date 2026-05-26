<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginErrorRecorderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\{PluginError, PluginState};
use App\Plugins\PluginErrorRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PluginErrorRecorderTest extends TestCase {
    use RefreshDatabase;

    public function test_record_persists_error_and_increments_failure_count(): void {
        $recorder = app(PluginErrorRecorder::class);

        $recorder->record('demo', 'boot', new RuntimeException('boom'));

        $this->assertSame(1, PluginError::query()->count());
        $state = PluginState::query()->where('plugin_id', 'demo')->firstOrFail();
        $this->assertSame(1, (int) $state->failure_count);
        $this->assertNull($state->disabled_reason);
    }

    public function test_record_auto_disables_after_threshold(): void {
        config()->set('plugins.auto_disable_threshold', 3);
        $recorder = app(PluginErrorRecorder::class);

        for ($i = 0; $i < 3; $i++) {
            $recorder->record('flaky', 'runtime', new RuntimeException('try ' . $i));
        }

        $state = PluginState::query()->where('plugin_id', 'flaky')->firstOrFail();
        $this->assertSame(3, (int) $state->failure_count);
        $this->assertNotNull($state->disabled_reason);
        $this->assertTrue($state->isAutoDisabled());
    }

    public function test_reset_clears_failures_and_disable_reason(): void {
        config()->set('plugins.auto_disable_threshold', 2);
        $recorder = app(PluginErrorRecorder::class);
        $recorder->record('p', 'boot', new RuntimeException('x'));
        $recorder->record('p', 'boot', new RuntimeException('y'));

        $recorder->reset('p');

        $state = PluginState::query()->where('plugin_id', 'p')->firstOrFail();
        $this->assertSame(0, (int) $state->failure_count);
        $this->assertNull($state->disabled_reason);
    }

    public function test_mark_healthy_resets_counter(): void {
        $recorder = app(PluginErrorRecorder::class);
        $recorder->record('p', 'healthcheck', new RuntimeException('x'));

        $recorder->markHealthy('p');

        $state = PluginState::query()->where('plugin_id', 'p')->firstOrFail();
        $this->assertSame(0, (int) $state->failure_count);
    }
}
