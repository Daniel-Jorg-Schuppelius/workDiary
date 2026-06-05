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

        $this->assertCount(0, $manager->enabled());
        $this->assertCount(1, $manager->all(), 'all() must still surface disabled plugins for admin UI');
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
