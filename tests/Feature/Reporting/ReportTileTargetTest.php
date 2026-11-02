<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReportTileTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Platform\User;
use App\Services\Compliance\AttendanceComplianceChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

/**
 * Berichtskacheln springen auf den Drilldown-Abschnitt derselben Seite oder
 * filtern den Bericht (ux-pattern-katalog §4, Vollscan 2026-11-02 gap-06).
 */
final class ReportTileTargetTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_qualification_tiles_jump_to_the_table(): void {
        $this->actingAs($this->admin)->get(route('reports.qualifications'))->assertOk()
            ->assertSee('href="#qualifications-table"', false)
            ->assertSee('id="qualifications-table"', false);
    }

    public function test_arbzg_kind_tiles_filter_the_report_and_mark_the_active_kind(): void {
        $kind = AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS;

        $this->actingAs($this->admin)->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance'))->assertOk()
            ->assertSee('href="#arbzg-findings"', false)
            ->assertSee('id="arbzg-findings"', false)
            ->assertSee(route('reports.arbzg-compliance', ['kind' => $kind]))
            ->assertDontSee('ring-primary/40');

        $this->actingAs($this->admin)->withSession($this->dateRangeYear(2030))
            ->get(route('reports.arbzg-compliance', ['kind' => $kind]))->assertOk()
            ->assertSee('ring-primary/40');
    }

    public function test_compliance_dashboard_tiles_lead_to_team_table_and_single_report(): void {
        $this->actingAs($this->admin)->withSession($this->dateRangeYear(2030))
            ->get(route('reports.compliance.dashboard'))->assertOk()
            ->assertSee('href="#compliance-by-team"', false)
            ->assertSee('id="compliance-by-team"', false)
            ->assertSee(route('reports.arbzg-compliance'));
    }
}
