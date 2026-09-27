<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SiteBenchmarkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Models\Platform\User;
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilityFactorSet, SustainabilitySite};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-929: Standorte mit Bezugsgrößen und Emissionen je Standort. */
final class SiteBenchmarkTest extends TestCase {
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

    private function activity(SustainabilitySite $site, string $code, string $amount): void {
        $this->actingAs($this->admin)->post(route('sustainability.activities.store'), [
            'site_id' => $site->sqid, 'activity_code' => $code, 'amount' => $amount, 'unit' => 'x', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'data_quality' => 'measured',
        ])->assertRedirect();
    }

    public function test_emissions_and_intensities_per_site(): void {
        $this->actingAs($this->admin)->post(route('sustainability.sites.store'), ['name' => 'Werk Nord', 'area_m2' => '2000', 'headcount' => 40])->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('sustainability.sites.store'), ['name' => 'Büro Süd', 'area_m2' => '500', 'headcount' => 10])->assertSessionHas('success');
        [$north, $south] = [SustainabilitySite::query()->where('name', 'Werk Nord')->sole(), SustainabilitySite::query()->where('name', 'Büro Süd')->sole()];

        $this->activity($north, 'electricity_kwh', '100000');
        $this->activity($south, 'electricity_kwh', '5000');
        $this->activity($south, 'water_m3', '10');

        $record = SustainabilityActivityRecord::query()->where('subject_id', $north->id)->sole();
        $this->assertSame('Werk Nord', $record->subject_label);

        // Nord: 40.000 kg → 20 kg/m², 1.000 kg/Person; Süd: 2.000 kg → 4 kg/m², ein Datensatz ohne Faktor.
        $html = (string) $this->actingAs($this->admin)->get(route('sustainability.sites.benchmark', ['year' => 2026]))->assertOk()->getContent();
        $this->assertStringContainsString('40,00', $html);
        $this->assertStringContainsString('20,00', $html);
        $this->assertStringContainsString('1.000,0', $html);
        $this->assertStringContainsString('4,00', $html);
        $this->assertLessThan(strpos($html, 'Büro Süd'), strpos($html, 'Werk Nord'));
    }

    public function test_foreign_sites_cannot_be_used(): void {
        $other = \App\Models\Platform\Organization::factory()->create();
        $foreign = SustainabilitySite::query()->create(['organization_id' => $other->id, 'name' => 'Fremd']);
        $this->actingAs($this->admin)->post(route('sustainability.activities.store'), [
            'site_id' => $foreign->sqid, 'activity_code' => 'electricity_kwh', 'amount' => '1', 'unit' => 'kWh', 'period_start' => '2026-03-01', 'period_end' => '2026-03-31', 'data_quality' => 'measured',
        ])->assertSessionHasErrors('site_id');
    }
}
