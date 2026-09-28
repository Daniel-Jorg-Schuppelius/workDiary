<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SustainabilityActivityImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sustainability;

use App\Enums\Import\ImportEntity;
use App\Models\Customer\Customer;
use App\Models\Sustainability\{SustainabilityActivityRecord, SustainabilitySite};
use App\Services\Import\{EntitySpecRegistry, ImportOutcome};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-973: ESG-Verbräuche per Datei über die Import-Drehscheibe. */
final class SustainabilityActivityImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_rows_create_records_with_subject_and_skip_duplicates(): void {
        $this->setUpOrganization();
        $site = SustainabilitySite::query()->create(['organization_id' => $this->organization->id, 'name' => 'Werk Nord', 'code' => 'WN', 'is_active' => true]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-100']);
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::SustainabilityActivities);

        $row = $spec->normalize(['activity_code' => 'Strom', 'amount' => '1.250,5', 'period_start' => '01.01.2026', 'period_end' => '31.01.2026', 'data_quality' => 'gemessen', 'site' => 'WN']);
        $this->assertSame([], $spec->validateRow($row, $this->organization));
        $this->assertSame('electricity_kwh', $row['activity_code']);
        $this->assertSame('kWh', $row['unit']);
        $this->assertSame(ImportOutcome::Created, $spec->upsert($row, $this->organization)[0]);
        $this->assertSame(ImportOutcome::Skipped, $spec->upsert($row, $this->organization)[0]);

        $water = $spec->normalize(['activity_code' => 'water_m3', 'amount' => '12', 'unit' => 'm³', 'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'data_quality' => 'estimated', 'customer' => 'K-100']);
        $this->assertSame(ImportOutcome::Created, $spec->upsert($water, $this->organization)[0]);

        $records = SustainabilityActivityRecord::query()->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame($site->getMorphClass(), $records[0]->subject_type);
        $this->assertSame('Werk Nord', $records[0]->subject_label);
        $this->assertSame('1250.500', (string) $records[0]->amount);
        $this->assertSame('measured', $records[0]->data_quality);
        $this->assertSame($customer->id, (int) $records[1]->subject_id);
        $this->assertSame('estimated', $records[1]->data_quality);
    }

    public function test_quality_is_required_and_unknown_codes_and_subjects_are_reported(): void {
        $this->setUpOrganization();
        $spec = app(EntitySpecRegistry::class)->for(ImportEntity::SustainabilityActivities);

        $issues = $spec->validateRow($spec->normalize(['activity_code' => 'Kohle', 'amount' => '5', 'period_start' => '2026-02-01', 'period_end' => '2026-01-01', 'data_quality' => '']), $this->organization);
        $fields = array_map(static fn ($issue) => $issue->field, $issues);
        $this->assertContains('data_quality', $fields);
        $this->assertContains('activity_code', $fields);
        $this->assertContains('period_end', $fields);

        [$outcome, $issue] = $spec->upsert($spec->normalize(['activity_code' => 'diesel_l', 'amount' => '40', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'data_quality' => 'calculated', 'site' => 'Unbekannt']), $this->organization);
        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(__('import.error.fkMissing.site', ['value' => 'Unbekannt']), $issue?->message);
        $this->assertSame(0, SustainabilityActivityRecord::query()->count());
    }
}
