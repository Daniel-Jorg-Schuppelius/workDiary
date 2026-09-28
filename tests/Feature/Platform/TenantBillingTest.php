<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TenantBillingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\Platform\TenantPlanRequestStatus;
use App\Models\Platform\{Organization, TenantPlanRequest, TenantUsageSnapshot, User};
use App\Services\Platform\TenantBillingService;
use App\Settings\{SettingScope, SettingsRegistry};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-956/957/958: Nutzungsabrechnung, Abrechnungsdaten, Tarifanfragen und UI-Bausteine. */
final class TenantBillingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $operator;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->operator = User::factory()->platformAdmin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_monthly_snapshot_is_priced_with_the_operator_settings(): void {
        $registry = app(SettingsRegistry::class);
        $registry->set('platform_billing.base_fee', '10', SettingScope::System);
        $registry->set('platform_billing.per_user', '5', SettingScope::System);
        $registry->set('platform_billing.per_gb', '1.5', SettingScope::System);
        $other = Organization::factory()->create(['name' => 'Kunde Zwei GmbH', 'plan' => 'pro']);
        User::factory()->count(3)->create(['organization_id' => $other->id]);
        Organization::factory()->create(['name' => 'Demo AG', 'is_demo' => true]);

        $count = app(TenantBillingService::class)->snapshotAll(CarbonImmutable::parse('2026-08-01'));
        $this->assertSame(2, $count);
        $snapshot = TenantUsageSnapshot::query()->where('organization_id', $other->id)->sole();
        $this->assertSame(3, $snapshot->users);
        $this->assertSame('pro', $snapshot->plan);
        $this->assertSame('25.00', (string) $snapshot->amount);   // 10 + 3 × 5, kein Speicher

        app(TenantBillingService::class)->snapshotAll(CarbonImmutable::parse('2026-08-15'));
        $this->assertSame(2, TenantUsageSnapshot::query()->count());

        $this->actingAs($this->operator)->get(route('admin.organizations.billing'))->assertOk()->assertSeeText('Kunde Zwei GmbH')->assertSeeText('25,00');
        $csv = (string) $this->actingAs($this->operator)->get(route('admin.organizations.billing', ['month' => '2026-08', 'export' => 'csv']))->assertOk()->getContent();
        $this->assertStringContainsString('Kunde Zwei GmbH', $csv);
        $this->artisan('platform:usage-snapshot', ['--month' => '2026-07'])->expectsOutputToContain('2 Organisationen')->assertSuccessful();

        $this->actingAs($this->orgAdmin())->get(route('admin.organizations.billing'))->assertForbidden();
    }

    public function test_tenant_maintains_billing_details_and_requests_a_plan_change(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('admin.billing-profile.show'))->assertOk()->assertSeeText(__('platform_usage.plan_request.title'));
        $this->actingAs($admin)->put(route('admin.billing-profile.update'), ['name' => 'Muster GmbH', 'email' => 'rechnung@example.org', 'vat_id' => 'XX123'])->assertSessionHasErrors('vat_id');
        $this->actingAs($admin)->put(route('admin.billing-profile.update'), ['name' => 'Muster GmbH', 'email' => 'rechnung@example.org', 'city' => 'Köln', 'vat_id' => 'DE 123 456 789'])->assertSessionHas('success');
        $this->assertSame('DE123456789', $this->organization->fresh()->settings['billing_contact']['vat_id']);

        $this->actingAs($admin)->post(route('admin.billing-profile.plan-request'), ['requested_plan' => 'enterprise', 'requested_addons' => 'module.rental, module.club'])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.billing-profile.plan-request'), ['requested_plan' => 'pro'])->assertSessionHasErrors('requested_plan');
        $request = TenantPlanRequest::query()->sole();
        $this->assertSame(['module.rental', 'module.club'], $request->requested_addons);

        $this->actingAs($this->operator)->get(route('admin.organizations.usage'))->assertOk()->assertSeeText(__('platform_usage.plan_request.open_title'));
        $this->actingAs($this->operator)->post(route('admin.organizations.plan-requests.decide', $request->sqid), ['decision' => 'done'])->assertSessionHas('success');
        $this->assertSame(TenantPlanRequestStatus::Done, $request->fresh()->status);

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->get(route('admin.billing-profile.show'))->assertForbidden();
    }

    public function test_ui_patterns_are_for_the_operator_only(): void {
        $this->actingAs($this->operator)->get(route('admin.ui-patterns.index'))->assertOk()->assertSeeText(__('ui_patterns.section.buttons'));
        $this->actingAs($this->orgAdmin())->get(route('admin.ui-patterns.index'))->assertForbidden();
    }
}
