<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Enums\Vehicle\{VehicleOwnership, VehiclePropulsion, VehicleType};
use App\Models\Vehicle;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\VehicleSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class VehicleSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_normalize_uppercases_plate_and_coerces_numbers_and_enums(): void {
        $spec = new VehicleSpec();

        $row = $spec->normalize([
            'license_plate' => '  b-xy 123 ',
            'label' => '  Sprinter ',
            'vehicle_type' => 'Van',
            'propulsion' => 'Diesel',
            'ownership' => 'Owned',
            'odometer_km' => '123456',
            'wltp_consumption' => '6,5',
        ]);

        $this->assertSame('B-XY 123', $row['license_plate']);
        $this->assertSame('Sprinter', $row['label']);
        $this->assertSame('van', $row['vehicle_type']);
        $this->assertSame('diesel', $row['propulsion']);
        $this->assertSame('owned', $row['ownership']);
        $this->assertSame(123456, $row['odometer_km']);
        $this->assertSame('6.5', $row['wltp_consumption']);
    }

    public function test_validate_row_reports_required_plate_and_invalid_enum(): void {
        $spec = new VehicleSpec();

        $row = $spec->normalize([
            'license_plate' => '',
            'vehicle_type' => 'spaceship',
        ]);

        $issues = $spec->validateRow($row, $this->organization);
        $codes = array_map(static fn($i) => $i->code, $issues);

        $this->assertContains(ImportErrorCode::Required, $codes);
        $this->assertContains(ImportErrorCode::Format, $codes);
    }

    public function test_upsert_creates_then_updates_by_license_plate(): void {
        $spec = new VehicleSpec();

        $row = $spec->normalize([
            'license_plate' => 'B-AB 100',
            'label' => 'Caddy',
            'vehicle_type' => 'car',
            'propulsion' => 'petrol',
            'ownership' => 'owned',
        ]);

        [$outcome, $issue] = $spec->upsert($row, $this->organization);
        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);
        $this->assertDatabaseHas('vehicles', [
            'organization_id' => $this->organization->id,
            'license_plate' => 'B-AB 100',
            'label' => 'Caddy',
        ]);

        $row2 = $spec->normalize([
            'license_plate' => 'B-AB 100',
            'label' => 'Caddy Maxi',
            'vehicle_type' => 'van',
        ]);
        [$outcome2] = $spec->upsert($row2, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome2);
        $this->assertSame(1, Vehicle::query()
            ->where('organization_id', $this->organization->id)
            ->where('license_plate', 'B-AB 100')
            ->count());
        $vehicle = Vehicle::query()->where('license_plate', 'B-AB 100')->firstOrFail();
        $this->assertSame('Caddy Maxi', $vehicle->label);
        $this->assertSame(VehicleType::Van, $vehicle->vehicle_type);
    }

    public function test_upsert_defaults_enum_columns(): void {
        $spec = new VehicleSpec();

        $row = $spec->normalize(['license_plate' => 'B-DEF 1']);
        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $vehicle = Vehicle::query()->where('license_plate', 'B-DEF 1')->firstOrFail();
        $this->assertSame(VehicleType::Car, $vehicle->vehicle_type);
        $this->assertSame(VehiclePropulsion::Diesel, $vehicle->propulsion);
        $this->assertSame(VehicleOwnership::Owned, $vehicle->ownership);
    }
}
