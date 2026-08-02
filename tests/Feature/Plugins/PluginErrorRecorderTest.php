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

    /** E-1: Phase `manual` landet in der Inbox, zählt aber nie für den Auto-Disable. */
    public function test_manual_phase_records_error_without_counting(): void {
        config()->set('plugins.auto_disable_threshold', 1);
        $recorder = app(PluginErrorRecorder::class);
        \Illuminate\Support\Facades\Event::fake([\App\Events\PluginAutoDisabled::class]);

        $recorder->record('clicked', PluginError::PHASE_MANUAL, new RuntimeException('manual boom'));
        $recorder->record('clicked', PluginError::PHASE_MANUAL, new RuntimeException('manual boom 2'));

        $this->assertSame(2, PluginError::query()->where('plugin_id', 'clicked')->count());
        // Kein State-Write: weder Counter noch Auto-Disable — trotz Schwelle 1.
        $this->assertSame(0, PluginState::query()->where('plugin_id', 'clicked')->count());
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\PluginAutoDisabled::class);
    }

    /**
     * A6: Recovery unabhängig vom Health-Status-Übergang — markHealthy() nach
     * einem Auto-Disable feuert PluginRecovered, damit die kritische
     * Betriebsaufgabe geschlossen wird.
     */
    public function test_mark_healthy_dispatches_recovered_after_auto_disable(): void {
        config()->set('plugins.auto_disable_threshold', 1);
        $recorder = app(PluginErrorRecorder::class);
        $recorder->record('p', 'runtime', new RuntimeException('x'));
        $this->assertTrue(PluginState::query()->where('plugin_id', 'p')->firstOrFail()->isAutoDisabled());

        \Illuminate\Support\Facades\Event::fake([\App\Events\PluginRecovered::class]);
        $recorder->markHealthy('p');

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\PluginRecovered::class, fn($e) => $e->pluginId === 'p');
    }

    public function test_mark_healthy_without_auto_disable_stays_silent(): void {
        $recorder = app(PluginErrorRecorder::class);
        $recorder->record('p', 'healthcheck', new RuntimeException('x'));

        \Illuminate\Support\Facades\Event::fake([\App\Events\PluginRecovered::class]);
        $recorder->markHealthy('p');

        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\PluginRecovered::class);
    }

    /** W2c: identische Störung dedupliziert auf eine offene Zeile mit Zähler. */
    public function test_repeated_identical_errors_are_deduplicated(): void {
        $recorder = app(PluginErrorRecorder::class);

        $recorder->record('dup', 'runtime', new RuntimeException('same boom'));
        $recorder->record('dup', 'runtime', new RuntimeException('same boom'));
        $recorder->record('dup', 'runtime', new RuntimeException('OTHER boom'));

        $this->assertSame(2, PluginError::query()->where('plugin_id', 'dup')->count());
        $deduped = PluginError::query()->where('plugin_id', 'dup')->where('message', 'same boom')->firstOrFail();
        $this->assertSame(2, (int) $deduped->occurrences);
        // Auto-Disable-Zählung bleibt korrekt: 3 Vorfälle = failure_count 3.
        $this->assertSame(3, (int) PluginState::query()->where('plugin_id', 'dup')->firstOrFail()->failure_count);

        // Quittierte Störungen zählen als erledigt — der nächste Vorfall
        // eröffnet eine NEUE Zeile statt die alte fortzuschreiben.
        $deduped->update(['acknowledged_at' => now()]);
        $recorder->record('dup', 'runtime', new RuntimeException('same boom'));
        $this->assertSame(3, PluginError::query()->where('plugin_id', 'dup')->count());
    }

    /** W2b: Fehler außerhalb des Zeitfensters starten die Zählung neu. */
    public function test_failure_window_resets_expired_counter(): void {
        config()->set('plugins.auto_disable_threshold', 3);
        config()->set('plugins.auto_disable_window_hours', 1);
        $recorder = app(PluginErrorRecorder::class);

        $recorder->record('windowed', 'runtime', new RuntimeException('a'));
        $recorder->record('windowed', 'runtime', new RuntimeException('b'));
        $this->assertSame(2, (int) PluginState::query()->where('plugin_id', 'windowed')->firstOrFail()->failure_count);

        $this->travel(2)->hours();
        $recorder->record('windowed', 'runtime', new RuntimeException('c'));

        $state = PluginState::query()->where('plugin_id', 'windowed')->firstOrFail();
        $this->assertSame(1, (int) $state->failure_count, 'Abgelaufenes Fenster startet die Zählung neu.');
        $this->assertNull($state->disabled_reason);
    }

    /** W2b: eigener (strengerer) Schwellwert für Boot-Fehler. */
    public function test_boot_threshold_overrides_default(): void {
        config()->set('plugins.auto_disable_threshold', 5);
        config()->set('plugins.auto_disable_boot_threshold', 1);
        $recorder = app(PluginErrorRecorder::class);

        $recorder->record('booting', 'boot', new RuntimeException('boot-crash'));

        $this->assertTrue(PluginState::query()->where('plugin_id', 'booting')->firstOrFail()->isAutoDisabled());
    }

    /** W2c: Aufbewahrung — quittierte nach 30, offene nach 90 Tagen prunebar. */
    public function test_prunable_selects_expired_errors(): void {
        $recorder = app(PluginErrorRecorder::class);
        $recorder->record('prune', 'runtime', new RuntimeException('alt-quittiert'));
        $recorder->record('prune', 'runtime', new RuntimeException('alt-offen'));
        $recorder->record('prune', 'runtime', new RuntimeException('frisch'));

        PluginError::query()->where('message', 'alt-quittiert')->update(['acknowledged_at' => now()->subDays(31)]);
        PluginError::query()->where('message', 'alt-offen')->update(['occurred_at' => now()->subDays(91)]);

        $prunable = (new PluginError)->prunable()->pluck('message')->all();
        sort($prunable);
        $this->assertSame(['alt-offen', 'alt-quittiert'], $prunable);
    }
}
