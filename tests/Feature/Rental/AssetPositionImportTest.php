<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetPositionImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Import\ImportEntity;
use App\Enums\Notification\NotificationEvent;
use App\Enums\Rental\RentalCaseStatus;
use App\Models\Asset\{Asset, AssetPosition};
use App\Models\Customer\Customer;
use App\Models\Facility\Site;
use App\Models\Location\CustomerGeofence;
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalCaseAsset};
use App\Services\Import\{EntitySpecRegistry, ImportOutcome};
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-975: Gerätepositionen aus Telematik-Exporten mit Abweichung vom Einsatzort. */
final class AssetPositionImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Asset $asset;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'serial_no' => 'SN-BAG-1']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function rent(?Site $site = null): RentalCase {
        $case = RentalCase::query()->create([
            'organization_id' => $this->organization->id, 'number' => 'VL-2026-0001', 'status' => RentalCaseStatus::HandedOver->value,
            'customer_id' => $this->customer->id, 'site_id' => $site?->id, 'starts_at' => '2026-09-01 06:00:00', 'ends_at' => '2026-09-30 18:00:00',
            'responsible_user_id' => User::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
        RentalCaseAsset::query()->create(['organization_id' => $this->organization->id, 'rental_case_id' => $case->id, 'asset_id' => $this->asset->id, 'status' => 'handed_over']);

        return $case;
    }

    public function test_positions_update_the_asset_and_skip_duplicates(): void {
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::AssetPositions);
        $first = $spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-09-02T08:00:00Z', 'lat' => '52,5200', 'lng' => '13,4050']);
        $this->assertSame([], $spec->validateRow($first, $this->organization));
        $this->assertSame(ImportOutcome::Created, $spec->upsert($first, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Skipped, $spec->upsert($first, $this->organization)[0]);

        // Ältere Position ändert den Gerätestand nicht.
        $this->assertSame(ImportOutcome::Created, $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '01.09.2026 10:00', 'lat' => '48.1', 'lng' => '11.5']), $this->organization)[0]);

        $this->assertSame('52.5200000', (string) $this->asset->fresh()->location_lat);
        $this->assertSame(2, AssetPosition::query()->count());
        $this->assertSame('2026-09-02 08:00:00', AssetPosition::query()->orderByDesc('recorded_at')->first()?->recorded_at->toDateTimeString());
        $this->assertNotEmpty($spec->validateRow($spec->normalize(['asset' => 'SN-BAG-1', 'date' => '2026-09-01', 'lat' => '95', 'lng' => '13']), $this->organization));
    }

    public function test_leaving_the_rental_site_is_reported_once(): void {
        $site = Site::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'name' => 'Baustelle Mitte', 'geo_lat' => '52.5200000', 'geo_lng' => '13.4050000']);
        $case = $this->rent($site);
        $notifier = Mockery::mock(NotificationDispatcher::class);
        $notifier->shouldReceive('notify')->once()->withArgs(fn (NotificationEvent $event, $subject) => $event === NotificationEvent::RentalGeofenceDeviation && $subject->is($case))->andReturn(1);
        $this->app->instance(NotificationDispatcher::class, $notifier);
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::AssetPositions);

        // Innerhalb des Radius (Standard 500 m), dann 2× deutlich außerhalb.
        $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-09-02T08:00:00Z', 'lat' => '52.5210', 'lng' => '13.4050']), $this->organization);
        $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-09-03T08:00:00Z', 'lat' => '52.6000', 'lng' => '13.4050']), $this->organization);
        $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-09-04T08:00:00Z', 'lat' => '52.6100', 'lng' => '13.4050']), $this->organization);

        $positions = AssetPosition::query()->orderBy('recorded_at')->get();
        $this->assertSame(0, $positions[0]->deviation_m);
        $this->assertSame('VL-2026-0001 · Baustelle Mitte', $positions[0]->expected_label);
        $this->assertGreaterThan(8000, (int) $positions[1]->deviation_m);
    }

    public function test_customer_geofence_is_the_fallback_and_no_rental_means_no_expectation(): void {
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::AssetPositions);
        $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-08-20T08:00:00Z', 'lat' => '50.0', 'lng' => '8.0']), $this->organization);
        $this->assertNull(AssetPosition::query()->sole()->deviation_m);

        CustomerGeofence::query()->create(['organization_id' => $this->organization->id, 'customer_id' => $this->customer->id, 'label' => 'Hof', 'center_lat' => '50.0000000', 'center_lng' => '8.0000000', 'radius_m' => 200, 'is_active' => true]);
        $this->rent();
        $spec->upsert($spec->normalize(['asset' => 'SN-BAG-1', 'timestamp' => '2026-09-05T08:00:00Z', 'lat' => '50.0005', 'lng' => '8.0']), $this->organization);

        $position = AssetPosition::query()->orderByDesc('recorded_at')->first();
        $this->assertSame(0, $position?->deviation_m);
        $this->assertSame('VL-2026-0001 · Hof', $position?->expected_label);
    }
}
