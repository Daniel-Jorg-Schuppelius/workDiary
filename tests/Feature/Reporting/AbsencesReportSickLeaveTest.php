<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbsencesReportSickLeaveTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Sickness\SickLeaveKind;
use App\Models\{AuditLog, SickLeave, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\{WithGlobalDateRange, WithOrganization};
use Tests\TestCase;

class AbsencesReportSickLeaveTest extends TestCase {
    use RefreshDatabase;
    use WithGlobalDateRange;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_absences_report_sources_sick_days_from_sick_leaves(): void {
        // Krankmeldung 2026-05-04 (Mo) – 2026-05-08 (Fr) = 5 Werktage
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-08',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        // Stornierte Krankmeldung darf nicht zählen.
        SickLeave::factory()->cancelled()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-11',
            'end_date' => '2026-05-15',
        ]);

        $response = $this->getWithYearRange('reports.absences');

        $response->assertOk();
        $response->assertViewHas('rows', function (array $rows): bool {
            foreach ($rows as $row) {
                if ((int) $row['user']->id === (int) $this->user->id) {
                    return $row['sick_days'] === 5;
                }
            }

            return false;
        });
    }

    public function test_absences_csv_export_contains_report_meta_line(): void {
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-08',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $response = $this->getWithYearRange('reports.absences', ['export' => 'csv']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->getContent() ?: '';
        $this->assertStringContainsString('#report:absences', $body);
        $this->assertStringContainsString('Mitarbeiter', $body);

        // A9: Audit läuft jetzt zentral in csvWithMetadata — vorher audit-los.
        $audit = AuditLog::query()->where('event', 'report.exported')->latest('id')->firstOrFail();
        $changes = $audit->getAttribute('changes') ?? [];
        $this->assertSame('absences', $changes['report_code']);
        $this->assertSame('csv', $changes['format']);
        $this->assertTrue(is_string($changes['filter_hash'] ?? null));
    }

    public function test_sickness_report_route_renders(): void {
        SickLeave::factory()->create([
            'user_id' => $this->user->id,
            'start_date' => '2026-05-04',
            'end_date' => '2026-05-08',
            'kind' => SickLeaveKind::Initial->value,
        ]);

        $response = $this->getWithYearRange('reports.sickness');

        $response->assertOk();
    }

    /**
     * @param  array<string, string>  $parameters
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function getWithYearRange(string $routeName, array $parameters = []): TestResponse {
        return $this->actingAs($this->user)
            ->withSession($this->dateRangeYear(2026))
            ->get(route($routeName, $parameters));
    }
}
