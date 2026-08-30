<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetricsPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Services\Metrics\OperationsMetricsService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsPageTest extends TestCase {
    // Betriebsmetriken sind eine plattformweite Sicht ohne Mandanten-Kontext
    // (Sicherheitsscan 2026-08-23, S-02).

    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.metrics.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('admin.metrics.index'))->assertForbidden();
    }

    public function test_index_renders_for_admin_with_privacy_notice_and_version(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.metrics.index'))
            ->assertOk()
            ->assertSee(__('metrics.title.index'))
            // Telemetrie-Hinweis: Daten bleiben lokal, kein externes Senden.
            ->assertSee(__('metrics.privacy_notice'))
            ->assertSee('<span class="material-symbols-outlined" aria-hidden="true">monitoring</span>', false)
            // Versions-Anzeige (Feature 022) auf der Metrik-Seite.
            ->assertSee((string) config('app.version'));
    }

    public function test_transparency_section_lists_counter_catalogue_with_descriptions(): void {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.metrics.index'))
            ->assertOk()
            ->assertSee(__('metrics.section.transparency'))
            // Default: Zähler aktiv (lokal, Opt-out — MVP-337).
            ->assertSee(__('metrics.transparency.status_enabled'))
            ->assertSee(__('metrics.transparency.storage'))
            ->assertSee(__('metrics.transparency.retention'));

        foreach (OperationsMetricsService::FEATURE_COUNTERS as $key) {
            $description = __('metrics.counter.' . $key);
            // Jeder Katalog-Key braucht eine echte Beschreibung — sonst
            // stünde der rohe Übersetzungsschlüssel auf der Seite.
            $this->assertNotSame('metrics.counter.' . $key, $description);
            $response->assertSee($key)->assertSee($description);
        }
    }

    public function test_transparency_section_shows_disabled_state_and_settings_link(): void {
        $admin = User::factory()->platformAdmin()->create();
        Setting::set('telemetry.enabled', false, SettingScope::System);

        $this->actingAs($admin)
            ->get(route('admin.metrics.index'))
            ->assertOk()
            ->assertSee(__('metrics.transparency.status_disabled'))
            // Admin darf Einstellungen verwalten → Link zur Schalterstelle.
            ->assertSee(route('admin.settings.index', ['q' => 'telemetry.enabled']), false);
    }
}
