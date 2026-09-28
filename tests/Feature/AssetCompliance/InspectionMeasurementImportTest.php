<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InspectionMeasurementImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\AssetCompliance;

use App\Enums\Import\ImportEntity;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceProfile, AssetMeasurementValue};
use App\Models\Platform\User;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Services\Import\{EntitySpecRegistry, ImportOutcome};
use Database\Seeders\AssetComplianceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-974: Prüfmesswerte per Datei an vorhandenen Prüfungen. */
final class InspectionMeasurementImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_measurements_attach_to_the_inspection_of_that_day(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'inventory_no' => 'PM-17']);
        $this->seed(AssetComplianceCatalogSeeder::class);
        $service = app(AssetComplianceService::class);
        $profile = AssetComplianceProfile::query()->whereNull('organization_id')->where('code', 'dguv_v3_portable')->firstOrFail();
        $assignment = $service->assign($profile, $asset, $admin, ['last_done_on' => now()->subYear()->toDateString()]);
        $event = $service->recordInspection($assignment, $admin, ['result' => 'passed', 'performed_at' => '2026-09-10 09:30:00']);

        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::InspectionMeasurements);
        $row = $spec->normalize(['asset' => 'PM-17', 'date' => '10.09.2026', 'label' => 'Isolationswiderstand', 'value' => '12,5', 'unit' => 'MΩ', 'time' => '09:35']);
        $this->assertSame([], $spec->validateRow($row, $this->organization));
        $this->assertSame(ImportOutcome::Created, $spec->upsert($row, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Skipped, $spec->upsert($row, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Created, $spec->upsert($spec->normalize(['asset' => 'PM-17', 'date' => '2026-09-10', 'label' => 'Schutzleiterwiderstand', 'value' => '0.12', 'unit' => 'Ω']), $this->organization)[0]);

        $values = AssetMeasurementValue::query()->where('asset_inspection_event_id', $event->id)->orderBy('id')->get();
        $this->assertCount(2, $values);
        $this->assertSame('12.5000', (string) $values[0]->value);
        $this->assertSame($event->performed_at->toDateTimeString(), $values[1]->measured_at?->toDateTimeString());
        $this->assertTrue($asset->auditLogs()->where('event', 'assetCompliance.measurementImported')->exists());

        [$outcome, $issue] = $spec->upsert($spec->normalize(['asset' => 'PM-17', 'date' => '11.09.2026', 'label' => 'x', 'value' => '1']), $this->organization);
        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(__('import.error.fkMissing.inspection', ['date' => '2026-09-11', 'asset' => 'PM-17']), $issue?->message);
    }
}
