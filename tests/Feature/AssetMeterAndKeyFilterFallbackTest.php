<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : GitHub Copilot
 * Filename     : AssetMeterAndKeyFilterFallbackTest.php
 * License      : AGPL-3.0-or-later
 */

namespace Tests\Feature;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Models\{Asset, KeyHandover, MeterReading, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetMeterAndKeyFilterFallbackTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_key_handover_index_accepts_numeric_asset_filter_fallback(): void {
        $assetA = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $assetB = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $handoverA = KeyHandover::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $assetA->id,
            'direction' => KeyHandoverDirection::Out->value,
            'person_name' => 'Alice',
        ]);
        KeyHandover::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $assetB->id,
            'direction' => KeyHandoverDirection::Out->value,
            'person_name' => 'Bob',
        ]);

        $this->actingAs($this->admin)
            ->get(route('key-handovers.index', ['asset' => (string) $assetA->id]))
            ->assertOk()
            ->assertViewHas('handovers', static function ($handovers) use ($handoverA): bool {
                $items = $handovers->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $handoverA->id;
            });
    }

    public function test_key_handover_create_accepts_numeric_asset_prefill_fallback(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->get(route('key-handovers.create', ['asset' => (string) $asset->id]))
            ->assertOk()
            ->assertViewHas('presetAssetId', $asset->id);
    }

    public function test_meter_reading_index_accepts_numeric_asset_filter_fallback(): void {
        $assetA = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $assetB = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $readingA = MeterReading::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $assetA->id,
            'value' => 100.0,
        ]);
        MeterReading::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $assetB->id,
            'value' => 200.0,
        ]);

        $this->actingAs($this->admin)
            ->get(route('meter-readings.index', ['asset' => (string) $assetA->id]))
            ->assertOk()
            ->assertViewHas('readings', static function ($readings) use ($readingA): bool {
                $items = $readings->items();
                return count($items) === 1 && (int) $items[0]->id === (int) $readingA->id;
            });
    }

    public function test_meter_reading_create_accepts_numeric_asset_prefill_fallback(): void {
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->admin)
            ->get(route('meter-readings.create', ['asset' => (string) $asset->id]))
            ->assertOk()
            ->assertViewHas('presetAssetId', $asset->id);
    }
}
