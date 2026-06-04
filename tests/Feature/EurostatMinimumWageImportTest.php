<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EurostatMinimumWageImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{MinimumWageReference, User};
use App\Services\Payroll\EurostatMinimumWageImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class EurostatMinimumWageImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /** @return array<string, mixed> JSON-stat: 3 geo (DE, FR, EU-Aggregat) × 2 Halbjahre. */
    private function jsonStat(): array {
        return [
            'value' => [0 => 1500.0, 1 => 1600.0, 2 => 2000.0, 3 => 2050.0, 4 => 9999.0, 5 => 9999.0],
            'id' => ['currency', 'geo', 'time'],
            'size' => [1, 3, 2],
            'dimension' => [
                'currency' => ['category' => ['index' => ['EUR' => 0]]],
                'geo' => ['category' => ['index' => ['DE' => 0, 'FR' => 1, 'EU27_2020' => 2]]],
                'time' => ['category' => ['index' => ['2024-S1' => 0, '2024-S2' => 1]]],
            ],
        ];
    }

    public function test_importer_parses_and_upserts_excluding_aggregates(): void {
        $count = (new EurostatMinimumWageImporter)->ingest($this->jsonStat());

        // DE×2 + FR×2 = 4; EU-Aggregat (len != 2) ausgeschlossen.
        $this->assertSame(4, $count);
        $this->assertSame(4, MinimumWageReference::count());
        $this->assertFalse(MinimumWageReference::where('country', 'EU27_2020')->exists());

        $de1 = MinimumWageReference::where('country', 'DE')->whereDate('valid_from', '2024-01-01')->first();
        $this->assertSame('1500.00', (string) $de1->monthly_amount);
        // S2 → 01.07.
        $this->assertTrue(MinimumWageReference::where('country', 'DE')->whereDate('valid_from', '2024-07-01')->exists());
    }

    public function test_import_is_idempotent(): void {
        $importer = new EurostatMinimumWageImporter;
        $importer->ingest($this->jsonStat());
        $importer->ingest($this->jsonStat());

        $this->assertSame(4, MinimumWageReference::count());
    }

    public function test_command_imports_via_http(): void {
        Http::fake(['ec.europa.eu/*' => Http::response($this->jsonStat(), 200)]);

        $this->artisan('payroll:import-minimum-wages')->assertSuccessful();

        $this->assertSame(4, MinimumWageReference::count());
    }

    public function test_hr_can_trigger_import_button(): void {
        Http::fake(['ec.europa.eu/*' => Http::response($this->jsonStat(), 200)]);
        $hr = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($hr)
            ->post(route('payroll.references.import'))
            ->assertRedirect(route('payroll.index'));

        $this->assertSame(4, MinimumWageReference::count());
    }

    public function test_normal_user_cannot_trigger_import(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)
            ->post(route('payroll.references.import'))
            ->assertForbidden();
    }
}
