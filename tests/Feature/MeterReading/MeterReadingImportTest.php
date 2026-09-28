<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterReadingImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\MeterReading;

use App\Enums\Import\ImportEntity;
use App\Models\Asset\{Asset, MeterReading};
use App\Services\Import\{EntitySpecRegistry, ImportOutcome};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-962: Zählerstände per Datei über den Import. */
final class MeterReadingImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_rows_create_readings_skip_duplicates_and_reject_decreasing_values(): void {
        $this->setUpOrganization();
        $asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'inventory_no' => 'BAG-07']);
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::MeterReadings);
        $row = fn (array $raw): array => $spec->normalize($raw);

        $first = $row(['asset' => 'BAG-07', 'date' => '01.09.2026', 'time' => '07:00', 'value' => '1.250,5', 'unit' => 'h']);
        $this->assertSame([], $spec->validateRow($first, $this->organization));
        $this->assertSame(ImportOutcome::Created, $spec->upsert($first, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Skipped, $spec->upsert($first, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Created, $spec->upsert($row(['asset' => 'BAG-07', 'date' => '08.09.2026', 'value' => '1300', 'unit' => 'h']), $this->organization)[0]);

        [$outcome, $issue] = $spec->upsert($row(['asset' => 'BAG-07', 'date' => '10.09.2026', 'value' => '900', 'unit' => 'h']), $this->organization);
        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(__('import.error.meterReading.decreasing'), $issue?->message);
        $this->assertSame(ImportOutcome::Failed, $spec->upsert($row(['asset' => 'UNBEKANNT', 'date' => '10.09.2026', 'value' => '1', 'unit' => 'h']), $this->organization)[0]);

        $readings = MeterReading::query()->where('asset_id', $asset->id)->orderBy('read_at')->get();
        $this->assertCount(2, $readings);
        $this->assertSame('49.5000', (string) $readings[1]->consumption);
        $this->assertNull($readings[0]->read_by_user_id);
        $this->assertNotEmpty($spec->validateRow($row(['asset' => '', 'date' => '', 'value' => '', 'unit' => '']), $this->organization));
    }
}
