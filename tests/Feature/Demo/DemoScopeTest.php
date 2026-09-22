<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Models\{LicenseFlagOverride, Organization, User};
use App\Services\Demo\DemoSeederService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-838: Die Demo folgt dem Funktionsumfang des Branchenprofils — die
 * Modul-Empfehlung wird angewandt, der Showcase legt nur für aktive Module
 * Daten an. „Vollumfang" schaltet alles frei; der Reset merkt sich die Wahl.
 */
final class DemoScopeTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_profile_scope_applies_the_module_recommendation_and_skips_foreign_blocks(): void {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::ItService);

        $this->assertSame('profile', $counts['showcase']);
        $this->assertSame(13, $counts['modules_active']);

        // IT empfiehlt Helpdesk und Agile, aber weder LMS, Bewerbungen noch Krise.
        $this->assertGreaterThan(0, (int) $counts['helpdesk_tickets']);
        $this->assertSame(2, $counts['agile_boards']);
        $this->assertSame(0, $counts['learning']);
        $this->assertSame(0, $counts['applications']);
        $this->assertSame(0, $counts['crisis_exercises']);
        $this->assertSame(0, $counts['local_accounting']);
        // Faktura gehört zu module.vertrieb und bleibt Teil der IT-Demo.
        $this->assertSame(1, $counts['invoices']);

        // Modulumfang ist als Org-Overrides gesetzt und auditiert.
        $overrides = LicenseFlagOverride::query()->where('organization_id', $organization->id)->pluck('flag');
        $this->assertContains('module.lms', $overrides->all());
        $this->assertContains('module.applications', $overrides->all());
        $this->assertNotContains('module.helpdesk', $overrides->all());
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'license.scopeConfigured']);
        $this->assertFalse((bool) ($organization->refresh()->settings['demo_full_showcase'] ?? true));
    }

    public function test_full_showcase_seeds_every_block_and_leaves_no_overrides(): void {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

        $counts = app(DemoSeederService::class)->seed($organization, $admin, DemoIndustry::ItService, true);

        $this->assertSame('full', $counts['showcase']);
        $this->assertSame(1, $counts['learning']);
        $this->assertGreaterThan(0, (int) $counts['applications']);
        $this->assertGreaterThan(0, (int) $counts['crisis_exercises']);
        $this->assertSame(0, LicenseFlagOverride::query()->where('organization_id', $organization->id)->count());
        $this->assertTrue((bool) ($organization->refresh()->settings['demo_full_showcase'] ?? false));
    }

    public function test_reset_keeps_the_chosen_scope_and_can_switch_it(): void {
        $organization = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $service = app(DemoSeederService::class);

        $service->seed($organization, $admin, DemoIndustry::ItService, true);
        $kept = $service->reset($organization->refresh(), $admin);
        $this->assertSame('full', $kept['showcase']);
        $this->assertSame(1, $kept['learning']);

        $switched = $service->reset($organization->refresh(), $admin, null, false);
        $this->assertSame('profile', $switched['showcase']);
        $this->assertSame(0, $switched['learning']);
        $this->assertGreaterThan(0, LicenseFlagOverride::query()->where('organization_id', $organization->id)->count());
    }

    public function test_commands_accept_the_showcase_option(): void {
        $org = Organization::factory()->create(['is_demo' => false]);
        User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->artisan('demo:seed', ['org' => $org->id, '--industry' => 'it-service', '--showcase' => 'full'])->assertSuccessful();
        $this->assertTrue((bool) ($org->refresh()->settings['demo_full_showcase'] ?? false));

        $this->artisan('demo:reset', ['org' => $org->id, '--showcase' => 'profile'])->assertSuccessful();
        $this->assertFalse((bool) ($org->refresh()->settings['demo_full_showcase'] ?? true));

        $this->artisan('demo:reset', ['org' => $org->id, '--showcase' => 'nonsense'])->assertFailed();

        $this->artisan('demo:fresh-org', ['--branche' => 'elektro', '--showcase' => 'full'])
            ->expectsOutputToContain('Vollumfang')
            ->assertSuccessful();
    }

    public function test_fresh_org_dialog_and_store_honour_the_full_showcase_flag(): void {
        $platformAdmin = User::factory()->admin()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->get(route('admin.demo.fresh-org.create'))
            ->assertOk()
            ->assertSee(__('Vollumfang vorführen'));

        $this->actingAs($platformAdmin)
            ->post(route('admin.demo.fresh-org.store'), ['industry' => 'it-service', 'full_showcase' => '1'])
            ->assertRedirect(route('admin.organizations.index', ['show_demo' => 1]));

        $demo = Organization::query()->where('is_demo', true)->latest('id')->firstOrFail();
        $this->assertTrue((bool) ($demo->settings['demo_full_showcase'] ?? false));
        $this->assertSame(0, LicenseFlagOverride::query()->where('organization_id', $demo->id)->count());
    }
}
