<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Damage;

use App\Enums\Damage\{DamageCaseStatus, DamageKind};
use App\Models\Claims\ClaimCase;
use App\Models\Customer\Customer;
use App\Models\Damage\DamageCase;
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Services\Claims\ClaimCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-919/920: Schadensfall-Baustein und Anbindung an die Akten. */
final class DamageCaseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClaimCase $claim;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->claim = app(ClaimCaseService::class)->open($this->organization, $this->admin, ['title' => 'Pumpe undicht', 'source' => 'manual', 'priority' => 'normal', 'severity' => 'minor', 'customer_id' => $customer->id]);
    }

    private function open(): DamageCase {
        $this->actingAs($this->admin)->post(route('damage-cases.store'), [
            'subject_type' => $this->claim->getMorphClass(),
            'subject' => $this->claim->sqid,
            'kind' => DamageKind::Liability->value,
            'title' => 'Wasserschaden beim Kunden',
            'occurred_at' => '2026-09-20T10:00',
            'insurer_name' => 'Muster Versicherung',
            'policy_number' => 'HP-1234',
            'estimated_amount' => '5000.00',
            'deductible_amount' => '500.00',
            'currency' => 'EUR',
        ])->assertRedirect();

        return DamageCase::query()->sole();
    }

    public function test_case_is_opened_on_the_record_with_number_and_journal(): void {
        $this->actingAs($this->admin)->get(route('damage-cases.create', ['subject_type' => $this->claim->getMorphClass(), 'subject' => $this->claim->sqid]))->assertOk()->assertSee('Pumpe undicht');

        $case = $this->open();

        $this->assertMatchesRegularExpression('/^SCH-\d{4}-\d{4}$/', (string) $case->number);
        $this->assertSame(DamageCaseStatus::Reported, $case->status);
        // Ortszeit der Organisation → UTC.
        $this->assertSame(\App\Support\Tz::parse('2026-09-20 10:00')->utc()->toDateTimeString(), $case->occurred_at?->toDateTimeString());
        $this->assertTrue($case->subject->is($this->claim));
        $this->assertSame(['opened'], $case->journal()->pluck('event')->all());
        $this->actingAs($this->admin)->get(route('claims.show', $this->claim))->assertOk()->assertSee((string) $case->number);
        $this->actingAs($this->admin)->get(route('damage-cases.show', $case))->assertOk()->assertSee('Muster Versicherung')->assertSee(__('journal.damage.opened'));
    }

    public function test_status_follows_the_contract_and_settlement_needs_an_amount(): void {
        $case = $this->open();
        $url = route('damage-cases.transition', $case);

        $this->actingAs($this->admin)->post($url, ['status' => 'settled', 'settled_amount' => '100'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post($url, ['status' => 'submitted', 'claim_number' => 'S-77'])->assertSessionHas('success');
        $this->actingAs($this->admin)->post($url, ['status' => 'settled'])->assertSessionHas('error', __('damage.error.settled_amount_required'));
        $this->actingAs($this->admin)->post($url, ['status' => 'settled', 'settled_amount' => '4200.00'])->assertSessionHas('success');

        $case->refresh();
        $this->assertSame(DamageCaseStatus::Settled, $case->status);
        $this->assertSame('S-77', $case->claim_number);
        $this->assertSame('3700.00', $case->netRecovery());
        $this->assertSame(['opened', 'status', 'status'], $case->journal()->pluck('event')->all());
    }

    public function test_list_filters_by_record_kind_and_sums_open_estimates(): void {
        $this->open();
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-XY 123']);
        $this->actingAs($this->admin)->post(route('damage-cases.store'), ['subject_type' => $vehicle->getMorphClass(), 'subject' => $vehicle->sqid, 'kind' => 'vehicle', 'title' => 'Parkrempler', 'currency' => 'EUR', 'estimated_amount' => '800'])->assertRedirect();

        $this->actingAs($this->admin)->get(route('damage-cases.index'))->assertOk()->assertSee('5.800,00');
        $this->actingAs($this->admin)->get(route('damage-cases.index', ['subject_type' => 'vehicles']))->assertOk()->assertSee('Parkrempler')->assertDontSee('Wasserschaden beim Kunden');
        $this->actingAs($this->admin)->get(route('vehicles.edit', $vehicle))->assertOk()->assertSee('Parkrempler');
    }

    public function test_access_needs_the_damage_permissions_and_the_record(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($user)->get(route('damage-cases.index'))->assertForbidden();
        $lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($lead)->get(route('damage-cases.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('damage-cases.create', ['subject_type' => 'customers', 'subject' => 'x']))->assertNotFound();
    }
}
