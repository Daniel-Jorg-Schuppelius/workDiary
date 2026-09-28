<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionTripBlockTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Fleet;

use App\Enums\AssetCompliance\AssetComplianceStatus;
use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Exceptions\LogbookViolationException;
use App\Models\Asset\Asset;
use App\Models\Diary\DiaryEntry;
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Services\Asset\Contracts\AssetComplianceStatusProvider;
use App\Services\Travel\TravelLogService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-994: Fristen-Ampel im Reservierungsformular und optionale Fahrtsperre bei überfälliger Prüfung. */
final class InspectionTripBlockTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Vehicle $vehicle;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Carbon::setTestNow('2030-06-20 10:00:00');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $this->vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'license_plate' => 'B-HU 994', 'asset_id' => $asset->id, 'logbook_mode' => false]);
        $this->app->instance(AssetComplianceStatusProvider::class, new class implements AssetComplianceStatusProvider {
            public function statusFor(Asset $asset): AssetComplianceStatus {
                return AssetComplianceStatus::Overdue;
            }

            public function syncOverdueBlocks(Asset $asset): int {
                return 0;
            }
        });
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function trip(string $date): void {
        app(TravelLogService::class)->create([
            'organization_id' => $this->organization->id, 'user_id' => $this->admin->id, 'vehicle_id' => $this->vehicle->id,
            'vehicle' => TravelLogVehicle::Company->value, 'trip_kind' => TripKind::Business->value, 'date' => $date,
            'distance_km' => 10, 'purpose' => 'Fahrt',
        ]);
    }

    public function test_trips_are_blocked_only_with_the_setting_and_only_from_today(): void {
        $this->trip('2030-06-20');

        Setting::set('fleet.block_trips_on_overdue_inspection', true, SettingScope::Organization, $this->organization);
        $this->trip('2030-06-10');

        try {
            $this->trip('2030-06-20');
            $this->fail('Fahrt trotz überfälliger Prüfung angelegt.');
        } catch (LogbookViolationException $e) {
            $this->assertArrayHasKey('vehicle_id', $e->errors);
        }
    }

    public function test_reservation_form_shows_the_inspection_light(): void {
        $entry = DiaryEntry::factory()->for($this->admin)->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)->get(route('diary.show', $entry))->assertOk()
            ->assertSee('B-HU 994 · ' . AssetComplianceStatus::Overdue->label())
            ->assertSee(__('dispatch.vehicle.inspection'));
    }
}
