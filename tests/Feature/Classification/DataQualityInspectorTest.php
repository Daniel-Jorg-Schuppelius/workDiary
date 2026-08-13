<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityInspectorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\{AuditLog, ClassificationRequirement, DiaryEntry, EntryType, Organization, User};
use App\Services\Classification\DataQualityInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataQualityInspectorTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private EntryType $entryType;

    private DataQualityInspector $inspector;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();
        // Der OrganizationObserver bootstrappt den Service-Typ bereits.
        $this->entryType = EntryType::query()->withoutGlobalScopes()
            ->where('organization_id', $this->org->id)
            ->where('slug', EntryType::SLUG_SERVICE)
            ->firstOrFail();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->actingAs($this->actor);

        $this->inspector = app(DataQualityInspector::class);
    }

    private function entry(): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->actor->id,
            'entry_type_id' => $this->entryType->id,
            'priority' => 'normal',
        ]);
    }

    public function test_reports_missing_required_classification_as_gap(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);

        $gaps = $this->inspector->diaryEntryGaps($this->entry());

        $this->assertCount(1, $gaps);
        $this->assertSame('defect_type', $gaps[0]['domain']);
        $this->assertTrue($gaps[0]['blocking']);
    }

    public function test_no_gaps_when_no_requirements_defined(): void {
        $this->assertSame([], $this->inspector->diaryEntryGaps($this->entry()));
    }

    public function test_report_aggregates_gaps_by_domain_phase_and_severity(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);

        $report = $this->inspector->report([$this->entry()]);

        $this->assertSame(1, $report['entries_with_gaps']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(1, $report['by_domain']['defect_type']['count']);
        $this->assertSame(1, $report['by_severity']['hard']);
        $this->assertSame(1, $report['by_phase']['beforeComplete']);
        $this->assertSame('defect_type', $report['rows'][0]['gaps'][0]['domain']);
        $this->assertSame('beforeComplete', $report['rows'][0]['gaps'][0]['phase']);
    }

    public function test_report_skips_entries_without_gaps(): void {
        $report = $this->inspector->report([$this->entry()]);

        $this->assertSame(0, $report['entries_with_gaps']);
        $this->assertSame([], $report['rows']);
        $this->assertSame([], $report['by_domain']);
    }

    public function test_inspection_does_not_write_audit_logs(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);

        $entry = $this->entry();
        $before = AuditLog::query()->count();

        $this->inspector->diaryEntryGaps($entry);

        $this->assertSame(
            $before,
            AuditLog::query()->count(),
            'Datenqualitäts-Hinweise dürfen keine Audit-Einträge erzeugen.',
        );
    }
}
