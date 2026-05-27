<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\MeterReading;

use App\Models\{Asset, Organization, User};
use App\Services\MeterReading\MeterReadingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MeterReadingServiceTest extends TestCase {
    use RefreshDatabase;

    private MeterReadingService $service;

    private Organization $org;

    private User $actor;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();

        $this->service = new MeterReadingService;
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($this->actor);
        app()->instance('currentOrganization', $this->org);
    }

    public function test_first_reading_has_no_previous_or_consumption(): void {
        $reading = $this->service->record($this->asset, $this->actor, [
            'read_at' => '2026-06-01 09:00:00',
            'value' => 1000.5,
            'unit' => 'kWh',
        ]);

        $this->assertSame('1000.5000', $reading->value);
        $this->assertNull($reading->previous_value);
        $this->assertNull($reading->consumption);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'meter_reading.recorded',
            'auditable_id' => $reading->id,
        ]);
    }

    public function test_second_reading_computes_consumption_from_previous(): void {
        $this->service->record($this->asset, $this->actor, [
            'read_at' => '2026-06-01 09:00:00',
            'value' => 1000,
            'unit' => 'kWh',
        ]);

        $reading = $this->service->record($this->asset, $this->actor, [
            'read_at' => '2026-07-01 09:00:00',
            'value' => 1234.5,
            'unit' => 'kWh',
        ]);

        $this->assertSame('1000.0000', $reading->previous_value);
        $this->assertSame('234.5000', $reading->consumption);
    }

    public function test_reading_below_previous_value_is_rejected(): void {
        $this->service->record($this->asset, $this->actor, [
            'read_at' => '2026-06-01 09:00:00',
            'value' => 1000,
            'unit' => 'kWh',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->record($this->asset, $this->actor, [
            'read_at' => '2026-07-01 09:00:00',
            'value' => 999,
            'unit' => 'kWh',
        ]);
    }
}
