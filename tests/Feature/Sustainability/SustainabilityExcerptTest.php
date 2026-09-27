<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityExcerptTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Sustainability\{SustainabilityReportSnapshot, SustainabilityTarget};
use App\Services\Sustainability\SustainabilityExcerptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/** MVP-930: freigegebener Snapshot über Token-Link und im Kundenportal. */
final class SustainabilityExcerptTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private SustainabilityReportSnapshot $snapshot;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->snapshot = SustainabilityReportSnapshot::query()->create([
            'organization_id' => $this->organization->id, 'period_start' => '2026-01-01', 'period_end' => '2026-06-30',
            'data' => ['co2e_total_kg' => 123456.0, 'co2e_by_scope' => [1 => 23456.0, 2 => 100000.0], 'methodology' => ['factor_sets' => ['UBA 2026']]],
            'created_by' => $this->admin->id,
        ]);
        SustainabilityTarget::query()->create(['organization_id' => $this->organization->id, 'metric' => 'co2e', 'label' => 'Halbierung Strom', 'baseline_value' => 200, 'baseline_year' => 2024, 'target_value' => 100, 'target_year' => 2030, 'unit' => 't']);
    }

    private function logout(): void {
        auth()->logout();
        app()->forgetInstance('currentOrganization');
    }

    public function test_public_excerpt_needs_token_and_released_snapshot(): void {
        $this->actingAs($this->admin)->post(route('sustainability.excerpt.rotate'))->assertRedirect(route('sustainability.excerpt.edit'));
        $token = session('sustainability_excerpt_token');
        $this->assertIsString($token);
        $this->actingAs($this->admin)->get(route('sustainability.excerpt.edit'))->assertOk()->assertSee(route('sustainability-excerpt.public', $token));
        $this->actingAs($this->admin)->get(route('sustainability.index'))->assertOk()->assertSee(route('sustainability.excerpt.edit'));

        $this->actingAs($this->admin)->patch(route('sustainability.excerpt.toggle'), ['enabled' => 1])->assertRedirect();
        $this->logout();
        $this->get(route('sustainability-excerpt.public', $token))->assertNotFound();

        $this->actingAs($this->admin)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => $this->snapshot->sqid, 'targets' => 1])->assertSessionHas('success');
        $this->logout();
        $this->get(route('sustainability-excerpt.public', $token))->assertOk()
            ->assertSee('123,5 t CO₂e')->assertSee('100,0 t')->assertSee('Halbierung Strom')->assertSee('UBA 2026');
        $this->get(route('sustainability-excerpt.public', 'falsch'))->assertNotFound();

        $this->actingAs($this->admin)->patch(route('sustainability.excerpt.toggle'), ['enabled' => 0])->assertRedirect();
        $this->logout();
        $this->get(route('sustainability-excerpt.public', $token))->assertNotFound();
    }

    public function test_targets_are_optional_and_foreign_snapshot_is_rejected(): void {
        $this->actingAs($this->admin)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => $this->snapshot->sqid])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('sustainability.excerpt.rotate'));
        $token = session('sustainability_excerpt_token');
        $this->actingAs($this->admin)->patch(route('sustainability.excerpt.toggle'), ['enabled' => 1]);
        $this->logout();
        $this->get(route('sustainability-excerpt.public', $token))->assertOk()->assertDontSee('Halbierung Strom');

        $other = Organization::factory()->create();
        $foreign = SustainabilityReportSnapshot::query()->withoutGlobalScopes()->create(['organization_id' => $other->id, 'period_start' => '2026-01-01', 'period_end' => '2026-06-30', 'data' => ['co2e_total_kg' => 1.0], 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => (string) $foreign->id])->assertSessionHasErrors('snapshot_id');
    }

    public function test_released_excerpt_appears_in_customer_portal(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $this->assertSame([], app(SustainabilityExcerptService::class)->portalNotices($this->organization, $customer));

        $this->actingAs($this->admin)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => $this->snapshot->sqid])->assertSessionHas('success');
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->actingAs($portalUser, 'customer')->get(route('customer.dashboard'))->assertOk()
            ->assertSee('Nachhaltigkeitsauszug 01.01.2026 – 30.06.2026')->assertSee('123,5 t CO₂e');
    }

    public function test_link_management_needs_organization_update(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->post(route('sustainability.excerpt.rotate'))->assertForbidden();
        $this->actingAs($user)->put(route('sustainability.excerpt.publish'), ['snapshot_id' => $this->snapshot->sqid])->assertForbidden();
    }
}
