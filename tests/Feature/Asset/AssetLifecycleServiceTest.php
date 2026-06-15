<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLifecycleServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Asset;

use App\Enums\Asset\AssetStatus;
use App\Models\Asset;
use App\Services\Asset\AssetLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssetLifecycleServiceTest extends TestCase {
    private AssetLifecycleService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = new AssetLifecycleService();
    }

    private function asset(array $attributes): Asset {
        $asset = new Asset();
        $asset->forceFill($attributes);

        return $asset;
    }

    public function test_active_asset_is_in_operation(): void {
        $asset = $this->asset([
            'status' => AssetStatus::Active->value,
            'decommissioned_on' => null,
        ]);

        $this->assertSame(AssetLifecycleService::PHASE_IN_OPERATION, $this->service->phase($asset));
        $this->assertSame('success', $this->service->phaseTone($asset));
    }

    public function test_replaced_asset_is_retired(): void {
        $asset = $this->asset([
            'status' => AssetStatus::Replaced->value,
            'decommissioned_on' => null,
        ]);

        $this->assertSame(AssetLifecycleService::PHASE_RETIRED, $this->service->phase($asset));
    }

    public function test_decommissioned_status_or_date_yields_decommissioned_phase(): void {
        $byStatus = $this->asset([
            'status' => AssetStatus::Decommissioned->value,
            'decommissioned_on' => null,
        ]);
        $byDate = $this->asset([
            'status' => AssetStatus::Active->value,
            'decommissioned_on' => Carbon::yesterday()->toDateString(),
        ]);

        $this->assertSame(AssetLifecycleService::PHASE_DECOMMISSIONED, $this->service->phase($byStatus));
        $this->assertSame(AssetLifecycleService::PHASE_DECOMMISSIONED, $this->service->phase($byDate));
    }

    public function test_warranty_expired_detection(): void {
        $expired = $this->asset(['status' => AssetStatus::Active->value, 'warranty_until' => Carbon::yesterday()->toDateString()]);
        $valid = $this->asset(['status' => AssetStatus::Active->value, 'warranty_until' => Carbon::tomorrow()->toDateString()]);

        $this->assertTrue($this->service->warrantyExpired($expired));
        $this->assertFalse($this->service->warrantyExpired($valid));
    }

    public function test_summary_reports_in_service_days(): void {
        $asset = $this->asset([
            'status' => AssetStatus::Active->value,
            'commissioned_on' => Carbon::today()->subDays(10)->toDateString(),
            'decommissioned_on' => null,
            'warranty_until' => null,
        ]);

        $summary = $this->service->summary($asset, Carbon::today());

        $this->assertSame(AssetLifecycleService::PHASE_IN_OPERATION, $summary['phase']);
        $this->assertSame(10, $summary['in_service_days']);
        $this->assertFalse($summary['warranty_expired']);
    }
}
