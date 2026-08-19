<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglRequestIntervalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\PluginSetting;
use App\Plugins\Toggl\{TogglConfig, TogglPlugin};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tarifgebundene API-Drosselung des Toggl-Plugins: `api_plan` leitet den
 * Mindestabstand aus der Toggl-Stunden-Quota ab (Free 30/h → 120 s, …),
 * `request_interval` überschreibt ihn explizit; ohne Angabe gilt das
 * tarifunabhängige Burst-Limit (1 Request/s).
 */
class TogglRequestIntervalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @param array<string, mixed> $settings */
    private function resolveWith(array $settings): float {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => TogglPlugin::ID,
            'enabled' => true,
            'settings' => ['api_token' => 'test-token', ...$settings],
        ]);

        return TogglConfig::resolve($this->organization->id)['request_interval'];
    }

    public function test_without_plan_burst_limit_applies(): void {
        $this->assertSame(1.0, $this->resolveWith([]));
    }

    public function test_plan_derives_interval_from_hourly_quota(): void {
        $this->assertSame(120.0, $this->resolveWith(['api_plan' => 'free']));
    }

    public function test_unknown_plan_falls_back_to_burst_limit(): void {
        $this->assertSame(1.0, $this->resolveWith(['api_plan' => 'gibtsnicht']));
    }

    public function test_explicit_interval_overrides_plan(): void {
        $this->assertSame(2.5, $this->resolveWith(['api_plan' => 'free', 'request_interval' => '2.5']));
    }

    public function test_explicit_interval_is_raised_to_toolkit_minimum(): void {
        $this->assertSame(0.2, $this->resolveWith(['request_interval' => '0.05']));
    }
}
