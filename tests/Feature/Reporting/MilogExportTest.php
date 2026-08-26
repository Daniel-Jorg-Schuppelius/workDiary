<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MilogExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Models\{Attendance, Organization, User};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zoll-Nachweisexport §17 MiLoG (Feature 131, MVP-695): Beginn/Ende/Dauer je
 * Arbeitstag je Arbeitnehmer als CSV — Aggregation, Determinismus, Recht und
 * Mandantengrenze.
 */
class MilogExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        // Deterministische Zeitzone, damit Beginn/Ende als Wandzeit stabil sind.
        $this->setUpOrganization(['timezone' => 'UTC']);
        config(['timesheet.breaks.auto_apply' => false]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Anna Arbeit',
            'personnel_number' => 'P-0815',
        ]);
    }

    private function attendance(int $userId, string $date, string $start, string $end, int $break = 0, ?int $organizationId = null): Attendance {
        return Attendance::factory()->create([
            'organization_id' => $organizationId ?? $this->organization->id,
            'user_id' => $userId,
            'date' => $date,
            'started_at' => "$date $start",
            'ended_at' => "$date $end",
            'break_minutes_auto' => 0,
            'break_minutes_manual' => $break,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);
    }

    private function download(array $parameters = []): TestResponse {
        return $this->actingAs($this->admin)
            ->get(route('reports.milog-evidence', $parameters + ['from' => '2030-03-01', 'to' => '2030-03-31']));
    }

    public function test_plain_user_is_forbidden(): void {
        $this->actingAs($this->user)
            ->get(route('reports.milog-evidence', ['from' => '2030-03-01', 'to' => '2030-03-31']))
            ->assertForbidden();
    }

    public function test_export_contains_start_end_breaks_and_duration_per_day(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);

        $response = $this->download()->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('milog_nachweis_2030-03-01_2030-03-31.csv', (string) $response->headers->get('Content-Disposition'));

        $csv = (string) $response->getContent();
        $this->assertStringContainsString((string) __('compliance.milog.csv.personnel_number'), $csv);
        $line = $this->lineContaining($csv, '2030-03-04');
        $this->assertStringContainsString('Anna Arbeit', $line);
        $this->assertStringContainsString('P-0815', $line);
        // Beginn 06:00, Ende 17:00, 30 min Pause, Dauer netto 10:30.
        $this->assertStringContainsString('06:00', $line);
        $this->assertStringContainsString('17:00', $line);
        $this->assertStringContainsString('30', $line);
        $this->assertStringContainsString('10:30', $line);
    }

    public function test_multiple_stamps_per_day_are_aggregated(): void {
        // Zwei Spannen: 08–12 und 13–17 (30 min Pause) → Beginn 08:00, Ende 17:00, netto 7:30.
        $this->attendance($this->user->id, '2030-03-05', '08:00:00', '12:00:00');
        $this->attendance($this->user->id, '2030-03-05', '13:00:00', '17:00:00', 30);

        $csv = (string) $this->download()->assertOk()->getContent();
        $line = $this->lineContaining($csv, '2030-03-05');
        $this->assertStringContainsString('08:00', $line);
        $this->assertStringContainsString('17:00', $line);
        $this->assertStringContainsString('7:30', $line);
    }

    public function test_rows_are_sorted_by_user_and_date(): void {
        $second = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zita Zuletzt',
        ]);
        $this->attendance($second->id, '2030-03-03', '08:00:00', '12:00:00');
        $this->attendance($this->user->id, '2030-03-07', '08:00:00', '12:00:00');
        $this->attendance($this->user->id, '2030-03-04', '08:00:00', '12:00:00');

        $csv = (string) $this->download()->assertOk()->getContent();
        $anna4 = strpos($csv, '2030-03-04');
        $anna7 = strpos($csv, '2030-03-07');
        $zita = strpos($csv, 'Zita Zuletzt');
        $this->assertNotFalse($anna4);
        $this->assertNotFalse($anna7);
        $this->assertNotFalse($zita);
        // Anna (Name) vor Zita; innerhalb Anna: Datum aufsteigend.
        $this->assertLessThan($anna7, $anna4);
        $this->assertLessThan($zita, $anna7);
    }

    public function test_other_tenants_are_not_exported(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = User::factory()->user()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremde Firma',
        ]);
        $this->attendance($foreign->id, '2030-03-04', '08:00:00', '12:00:00', 0, (int) $otherOrg->id);

        $csv = (string) $this->download()->assertOk()->getContent();
        $this->assertStringNotContainsString('Fremde Firma', $csv);
    }

    public function test_user_filter_narrows_the_export(): void {
        $second = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zita Zuletzt',
        ]);
        $this->attendance($second->id, '2030-03-03', '08:00:00', '12:00:00');
        $this->attendance($this->user->id, '2030-03-04', '08:00:00', '12:00:00');

        $csv = (string) $this->download(['user' => Sqid::encode(User::class, (int) $this->user->id)])
            ->assertOk()->getContent();

        $this->assertStringContainsString('Anna Arbeit', $csv);
        $this->assertStringNotContainsString('Zita Zuletzt', $csv);
    }

    public function test_export_is_audited(): void {
        $this->attendance($this->user->id, '2030-03-04', '06:00:00', '17:00:00', 30);

        $this->download()->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'report.exported']);
    }

    private function lineContaining(string $csv, string $needle): string {
        foreach (preg_split('/\r\n|\n/', $csv) ?: [] as $line) {
            if (str_contains($line, $needle)) {
                return $line;
            }
        }
        $this->fail("Keine CSV-Zeile mit »{$needle}« gefunden.");
    }
}
