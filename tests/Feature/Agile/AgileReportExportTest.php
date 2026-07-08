<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileReportExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\{AuditLog, Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Feature 064, P11 (MVP-149): Drilldown nur signiert (403 ohne Signatur),
 * Summen-Konsistenz sichtbar, CSV-Export mit Exportkopf (metric_version/
 * Einheit/Stand) reproduzierbar bei fixiertem Datenstand, Export-Audit
 * report.exported, PDF-Download übers pdf-toolkit.
 */
final class AgileReportExportTest extends TestCase {
    use RefreshDatabase;

    private \App\Models\Agile\AgileBoard $board;

    private User $lead;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $org->id]);
        $this->project = Project::factory()->create(['organization_id' => $org->id]);
        $this->board = app(AgileBoardService::class)->activate($this->project, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $this->lead);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Ein erledigtes Element mit bekanntem Zeitpunkt als Fixture. */
    private function seedDoneItem(): string {
        $boards = app(AgileBoardService::class);
        Carbon::setTestNow('2026-07-01 09:00:00');
        $item = app(AgileWorkItemService::class)->create($this->board, ['title' => 'Export-Story', 'story_points' => 5], $this->lead);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        Carbon::setTestNow('2026-07-02 09:00:00');
        $boards->move($item->fresh(), $done, (int) $item->fresh()->lock_version, null, $this->lead);

        return Carbon::parse('2026-07-02')->format('o-\WW');
    }

    public function test_drilldown_requires_signature_and_checks_consistency(): void {
        $week = $this->seedDoneItem();
        Carbon::setTestNow();

        // Ohne Signatur: 403.
        $this->actingAs($this->lead)
            ->get(route('agile.reports.drilldown', ['project' => $this->project, 'kind' => 'throughput_week', 'key' => $week, 'expected' => 1]))
            ->assertForbidden();

        // Mit Signatur: Trefferliste, konsistent.
        $url = URL::temporarySignedRoute('agile.reports.drilldown', now()->addMinutes(30), [
            'project' => $this->project, 'kind' => 'throughput_week', 'key' => $week, 'expected' => 1,
        ]);
        $this->actingAs($this->lead)->get($url)
            ->assertOk()
            ->assertSee('Export-Story')
            ->assertDontSee(__('Konsistenz-Hinweis:'));

        // Falscher Erwartungswert → sichtbarer Konsistenz-Hinweis.
        $url = URL::temporarySignedRoute('agile.reports.drilldown', now()->addMinutes(30), [
            'project' => $this->project, 'kind' => 'throughput_week', 'key' => $week, 'expected' => 5,
        ]);
        $this->actingAs($this->lead)->get($url)
            ->assertOk()
            ->assertSee('Konsistenz-Hinweis');
    }

    public function test_csv_export_is_reproducible_and_audited(): void {
        $this->seedDoneItem();
        Carbon::setTestNow('2026-07-03 08:00:00'); // Datenstand fixiert

        $first = $this->actingAs($this->lead)
            ->get(route('agile.reports.export.csv', [$this->project, 'throughput']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $second = $this->actingAs($this->lead)
            ->get(route('agile.reports.export.csv', [$this->project, 'throughput']));

        // Reproduzierbar: identischer Inhalt bei fixiertem Datenstand.
        $this->assertSame($first->getContent(), $second->getContent());

        // Exportkopf: Reportcode + metric_version + Einheit + Stand.
        $content = (string) $first->getContent();
        $this->assertStringContainsString('#report:agile_throughput_v1', $content);
        $this->assertStringContainsString('metric_version', $content);
        $this->assertStringContainsString('items_per_week', $content);

        // Audit report.exported mit Filter-Hash.
        $audit = AuditLog::query()->where('event', 'report.exported')->get();
        $this->assertCount(2, $audit);
        $this->assertSame('agile_throughput', $audit->first()->getAttribute('changes')['report_code']);
    }

    public function test_pdf_export_downloads_and_unknown_metric_404(): void {
        $this->seedDoneItem();
        Carbon::setTestNow();

        $this->actingAs($this->lead)
            ->get(route('agile.reports.export.pdf', $this->project))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($this->lead)
            ->get(route('agile.reports.export.csv', [$this->project, 'unbekannt']))
            ->assertNotFound();
    }
}
