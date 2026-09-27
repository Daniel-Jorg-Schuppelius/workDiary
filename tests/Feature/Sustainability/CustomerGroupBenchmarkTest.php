<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerGroupBenchmarkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Classification\Classification;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityFactorSet};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-949: Kundengruppe am Kunden und Emissionen je Kundengruppe. */
final class CustomerGroupBenchmarkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $set = SustainabilityFactorSet::query()->create(['organization_id' => null, 'name' => 'Standard', 'source' => 'UBA', 'region' => 'DE', 'year' => 2026, 'active' => true]);
        $set->factors()->create(['activity_code' => 'electricity_kwh', 'label' => 'Strom', 'unit_code' => 'kg_co2e_per_kwh', 'factor' => '0.400000', 'scope' => 2, 'valid_from' => '2026-01-01', 'quality' => 'high']);
    }

    private function group(string $label): Classification {
        return Classification::query()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::CustomerGroup->value, 'code' => mb_strtolower($label), 'label' => $label, 'sort_order' => 1, 'active' => true]);
    }

    private function activity(Customer $customer, string $amount): void {
        $this->actingAs($this->admin)->post(route('sustainability.activities.store'), [
            'customer_id' => $customer->sqid, 'activity_code' => 'electricity_kwh', 'amount' => $amount, 'unit' => 'kWh', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'data_quality' => 'measured',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_customer_form_assigns_group_and_keeps_other_domains(): void {
        $industry = $this->group('Industrie');
        $trade = $this->group('Handel');
        $other = Classification::query()->create(['organization_id' => $this->organization->id, 'domain' => ClassificationDomain::WasteCode->value, 'code' => '170101', 'label' => 'Beton', 'sort_order' => 1, 'active' => true]);

        $this->actingAs($this->admin)->post(route('customers.store'), ['name' => 'Werk AG', 'currency' => 'EUR', 'customer_group_id' => $industry->sqid])->assertSessionHasNoErrors();
        $customer = Customer::query()->where('name', 'Werk AG')->sole();
        $customer->classifications()->attach($other->id);
        $this->assertSame([$industry->id, $other->id], $customer->classifications()->orderBy('classifications.id')->pluck('classifications.id')->all());

        $customer->syncClassificationDomain(ClassificationDomain::CustomerGroup, [$trade->id]);
        $this->assertSame([$trade->id, $other->id], $customer->classifications()->orderBy('classifications.id')->pluck('classifications.id')->all());

        $this->actingAs($this->admin)->get(route('customers.edit', $customer))->assertOk()->assertSee('Handel');
    }

    public function test_emissions_per_customer_group(): void {
        $industry = $this->group('Industrie');
        $a = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'A']);
        $b = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'B']);
        $a->syncClassificationDomain(ClassificationDomain::CustomerGroup, [$industry->id]);
        $b->syncClassificationDomain(ClassificationDomain::CustomerGroup, [$industry->id]);

        $this->activity($a, '10000');
        $this->activity($b, '5000');
        $this->assertSame(2, SustainabilityActivityRecord::query()->where('subject_type', $a->getMorphClass())->count());

        // 6.000 kg → 6,00 t, 3.000 kg je Kunde.
        $this->actingAs($this->admin)->get(route('sustainability.sites.benchmark', ['year' => 2026]))
            ->assertOk()
            ->assertSeeText(__('sustainability.customer_group.title', ['year' => 2026]))
            ->assertSeeText('Industrie')
            ->assertSeeText('6,00')
            ->assertSeeText('3.000,0 kg');
    }

    public function test_foreign_customers_cannot_be_referenced(): void {
        $foreign = Customer::factory()->create(['organization_id' => \App\Models\Platform\Organization::factory()->create()->id]);
        $this->actingAs($this->admin)->post(route('sustainability.activities.store'), [
            'customer_id' => $foreign->sqid, 'activity_code' => 'electricity_kwh', 'amount' => '1', 'unit' => 'kWh', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'data_quality' => 'measured',
        ])->assertSessionHasErrors('customer_id');
    }
}
