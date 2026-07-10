<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\{ClassificationRequirement, DiaryEntry, EntryType, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Feature 024 → Rang 57: Datenqualitäts-Report „Objekte ohne
 * Pflichtklassifikation". Prüft Aggregation je Domäne/Schwere, Zeitraumfilter
 * und die Report-Berechtigung (report.view).
 */
class DataQualityReportTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;
    private EntryType $entryType;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->entryType = EntryType::factory()->service()->create(['organization_id' => $this->organization->id]);

        ClassificationRequirement::factory()->create([
            'organization_id' => $this->organization->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);
    }

    private function entry(string $startAt): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'entry_type_id' => $this->entryType->id,
            'priority' => 'normal',
            'title' => 'Serviceeinsatz Halle 3',
            'start_at' => $startAt,
            'end_at' => $startAt,
        ]);
    }

    public function test_report_lists_entries_with_classification_gaps(): void {
        $this->entry('2026-06-10 08:00:00');
        // Auftrag außerhalb des Zeitraums bleibt außen vor.
        $this->entry('2026-04-10 08:00:00');

        $response = $this->actingAs($this->admin)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.data-quality'));

        $response->assertOk();
        $response->assertViewHas('entries_with_gaps', 1); // nur der Juni-Auftrag
        $bySeverity = $response->viewData('by_severity');
        $this->assertSame(1, $bySeverity['hard']);
        $byDomain = $response->viewData('by_domain');
        $this->assertSame(1, $byDomain['defect_type']['count']);
        $response->assertSee('Serviceeinsatz Halle 3');
    }

    public function test_report_requires_report_view_permission(): void {
        $plain = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($plain)
            ->withSession($this->dateRangeMonth(2026, 6))
            ->get(route('reports.data-quality'))
            ->assertForbidden();
    }
}
