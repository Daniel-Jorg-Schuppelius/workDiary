<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginManagerAutoDisableTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Models\PluginState;
use App\Plugins\Contracts\{Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginManager};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginManagerAutoDisableTest extends TestCase {
    use RefreshDatabase;

    public function test_enabled_excludes_auto_disabled_plugins(): void {
        $manager = new PluginManager;
        $manager->register(new FakeAlwaysOnPlugin);

        $this->assertCount(1, $manager->enabled());

        PluginState::create([
            'plugin_id' => 'fake',
            'disabled_reason' => 'forced',
        ]);

        // enabled() ist request-memoisiert (Review 2026-08, W2e); produktive
        // Schreibpfade flushen den app-gebundenen Manager automatisch (Model-
        // Hook) — diese Instanz ist nicht app-gebunden, daher explizit.
        $manager->flushRuntimeCaches();

        $this->assertCount(0, $manager->enabled());
        $this->assertCount(1, $manager->all(), 'all() must still surface disabled plugins for admin UI');
    }

    /** W2e: ein werfendes isEnabled() reißt keine Seite mehr — Plugin gilt als deaktiviert. */
    public function test_throwing_is_enabled_counts_as_disabled(): void {
        $manager = new PluginManager;
        $manager->register(new FakeThrowingEnabledPlugin);

        $this->assertCount(0, $manager->enabled());
        $this->assertCount(1, $manager->all());
        // Der Fehler landet in der Inbox (phase runtime).
        $this->assertSame(1, \App\Models\PluginError::query()->where('plugin_id', 'fake-throwing')->count());
    }
}

final class FakeThrowingEnabledPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'fake-throwing';
    }

    public function name(): string {
        return 'Fake Throwing';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return '';
    }

    public function isEnabled(): bool {
        throw new \RuntimeException('kaputte Settings-Deserialisierung');
    }

    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }

    public function adminPanel(): ?array {
        return null;
    }

    public function serviceProvider(): ?string {
        return null;
    }

    public function settingsSchema(): array {
        return [];
    }
}

final class FakeAlwaysOnPlugin implements Plugin {
    use PluginDefaults;

    public function id(): string {
        return 'fake';
    }

    public function name(): string {
        return 'Fake';
    }

    public function version(): string {
        return '1.0.0';
    }

    public function description(): string {
        return 'Test plugin';
    }

    public function isEnabled(): bool {
        return true;
    }

    public function capabilities(): array {
        return [PluginCapability::ContactSync];
    }

    public function adminPanel(): ?array {
        return null;
    }

    public function serviceProvider(): ?string {
        return null;
    }

    public function settingsSchema(): array {
        return [];
    }
}
