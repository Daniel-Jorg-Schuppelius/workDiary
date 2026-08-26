<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleInspectionBlockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Fleet;

use App\Enums\Asset\{AssetBlockReason, AssetClass};
use App\Enums\AssetCompliance\AssetComplianceBlockMode;
use App\Exceptions\VehicleInspectionOverdueException;
use App\Models\{Asset, AssetBlock, User, Vehicle};
use App\Models\AssetCompliance\AssetComplianceProfile;
use App\Services\Asset\AssetBlockService;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Dispatch\VehicleReservationService;
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 138 (MVP-703): Fahrzeug ↔ Asset-Zuordnung; überfällige HU/UVV aus
 * dem Prüfwesen sperren die Reservierung (D12-Sperrmodell, synchron
 * nachgezogen), Ausnahmefreigabe je Kontext öffnet, Fristen-Ampel in der
 * Fahrzeugliste.
 */
class VehicleInspectionBlockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $driver;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->seed(AssetComplianceCatalogSeeder::class);
        Carbon::setTestNow('2030-06-15 10:00:00');
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function huProfile(): AssetComplianceProfile {
        return AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'hu_vehicle')->firstOrFail();
    }

    /** @return array{Vehicle, Asset} */
    private function vehicleWithAsset(): array {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Transporter', 'asset_class' => AssetClass::Vehicle->value]);
        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-HU 138', 'asset_id' => $asset->id]);

        return [$vehicle, $asset];
    }

    public function test_vehicle_form_links_asset_by_sqid(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = Asset::factory()->create();

        $this->actingAs($this->admin)->post(route('vehicles.store'), [
            'license_plate' => 'B-AS 1',
            'vehicle_type' => 'car',
            'propulsion' => 'diesel',
            'asset_id' => $asset->sqid,
        ])->assertRedirect(route('vehicles.index'));
        $this->assertSame($asset->id, Vehicle::query()->where('license_plate', 'B-AS 1')->firstOrFail()->asset_id);

        // Fremdes Asset ist nicht zuordenbar (org-gescopte exists-Rule).
        $this->actingAs($this->admin)->from(route('vehicles.index'))->post(route('vehicles.store'), [
            'license_plate' => 'B-AS 2',
            'vehicle_type' => 'car',
            'propulsion' => 'diesel',
            'asset_id' => $foreign->sqid,
        ])->assertSessionHasErrors('asset_id');
    }

    public function test_overdue_hu_blocks_reservation_until_exception_or_inspection(): void {
        [$vehicle, $asset] = $this->vehicleWithAsset();
        $compliance = app(AssetComplianceService::class);
        $assignment = $compliance->assign($this->huProfile(), $asset, $this->admin, ['next_due_on' => '2030-05-01']);

        $service = app(VehicleReservationService::class);
        try {
            $service->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id);
            $this->fail('Erwartete VehicleInspectionOverdueException wurde nicht geworfen.');
        } catch (VehicleInspectionOverdueException $e) {
            $this->assertSame($vehicle->id, $e->vehicle->id);
            $this->assertSame(AssetBlockReason::InspectionOverdue, $e->block?->reason);
            $this->assertStringContainsString('B-HU 138', $e->getMessage());
        }
        // Sperre wurde synchron im D12-Modell angelegt (kein Warten auf den Scan), genau einmal.
        $this->assertSame(1, AssetBlock::query()->where('asset_id', $asset->id)->count());
        $this->assertDatabaseMissing('vehicle_reservations', ['vehicle_id' => $vehicle->id]);

        // Zweiter Versuch legt keine zweite Sperre an.
        try {
            $service->reserve($vehicle, '2030-06-17 08:00:00', '2030-06-17 17:00:00', (int) $this->driver->id);
        } catch (VehicleInspectionOverdueException) {
        }
        $this->assertSame(1, AssetBlock::query()->where('asset_id', $asset->id)->count());

        // Befristete, begründete Ausnahmefreigabe je Kontext (D12) öffnet die Reservierung.
        $block = AssetBlock::query()->where('asset_id', $asset->id)->firstOrFail();
        app(AssetBlockService::class)->grantException($block, $this->admin, VehicleReservationService::USAGE_CONTEXT, 'Überführung zur Prüfstelle am Folgetag', Carbon::parse('2030-06-20'));
        $reservation = $service->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id);
        $this->assertTrue($reservation->exists);

        // Bestandene Prüfung hebt die Sperre auf; Reservierung ohne Ausnahme möglich.
        $compliance->recordInspection($assignment->refresh(), $this->admin, [
            'result' => 'passed',
            'signature_name' => 'Prüfer',
            'certificate' => ['certificate_no' => 'HU-1', 'issuer' => 'Prüfstelle'],
        ]);
        $this->assertSame(0, AssetBlock::query()->where('asset_id', $asset->id)->active()->count());
        $reservation2 = $service->reserve($vehicle, '2030-06-18 08:00:00', '2030-06-18 17:00:00', (int) $this->driver->id);
        $this->assertTrue($reservation2->exists);
    }

    public function test_reservation_allowed_when_inspection_valid_or_without_asset(): void {
        [$vehicle, $asset] = $this->vehicleWithAsset();
        app(AssetComplianceService::class)->assign($this->huProfile(), $asset, $this->admin, ['next_due_on' => '2031-01-01']);

        $service = app(VehicleReservationService::class);
        $this->assertTrue($service->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id)->exists);
        $this->assertSame(0, AssetBlock::query()->where('asset_id', $asset->id)->count());

        $plain = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-OA 1']);
        $this->assertTrue($service->reserve($plain, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id)->exists);
    }

    public function test_profile_without_blocking_mode_only_warns(): void {
        [$vehicle, $asset] = $this->vehicleWithAsset();
        $profile = AssetComplianceProfile::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'internal_vehicle_check',
            'name' => 'Sichtkontrolle',
            'inspection_kind' => 'internal_check',
            'interval_months' => 1,
            'blocking_mode' => AssetComplianceBlockMode::Warn->value,
            'is_active' => true,
        ]);
        app(AssetComplianceService::class)->assign($profile, $asset, $this->admin, ['next_due_on' => '2030-05-01']);

        $reservation = app(VehicleReservationService::class)->reserve($vehicle, '2030-06-16 08:00:00', '2030-06-16 17:00:00', (int) $this->driver->id);
        $this->assertTrue($reservation->exists);
    }

    public function test_reservation_form_shows_block_message(): void {
        [$vehicle, $asset] = $this->vehicleWithAsset();
        app(AssetComplianceService::class)->assign($this->huProfile(), $asset, $this->admin, ['next_due_on' => '2030-05-01']);

        $this->actingAs($this->admin)->from(route('vehicle-reservations.index'))->post(route('vehicle-reservations.store'), [
            'vehicle_id' => $vehicle->sqid,
            'reserved_from' => '2030-06-16 08:00',
            'reserved_to' => '2030-06-16 17:00',
        ])->assertRedirect(route('vehicle-reservations.index'))->assertSessionHasErrors('vehicle_id');
        $this->assertDatabaseMissing('vehicle_reservations', ['vehicle_id' => $vehicle->id]);
    }

    public function test_vehicle_list_shows_inspection_traffic_light(): void {
        [$vehicle, $asset] = $this->vehicleWithAsset();
        app(AssetComplianceService::class)->assign($this->huProfile(), $asset, $this->admin, ['next_due_on' => '2030-05-01']);
        Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-OK 2']);

        $response = $this->actingAs($this->admin)->get(route('vehicles.index'));
        $response->assertOk()
            ->assertSee(__('Prüffristen'))
            ->assertSee(__('Prüfung überfällig'))
            ->assertSee('Hauptuntersuchung Kfz');
        $inspections = $response->viewData('inspections');
        $this->assertArrayHasKey($vehicle->id, $inspections);
        $this->assertSame('overdue', $inspections[$vehicle->id]['status']->value);
        $this->assertSame('2030-05-01', $inspections[$vehicle->id]['next_due_on']?->toDateString());
    }
}
