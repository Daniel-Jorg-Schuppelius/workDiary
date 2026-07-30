<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoreTimeComplianceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Compliance;

use App\Enums\Project\ProjectStatus;
use App\Models\{ComplianceFinding, Organization, Project, TimeEntry, User, WorkSchedule};
use App\Services\Flextime\CoreTimeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Vollreview W2.1: CoreTimeValidator ist verdrahtet — als Finding-Quelle im
 * Compliance-Scan und als nicht blockierende Warnung im Speicherpfad.
 */
class CoreTimeComplianceTest extends TestCase {
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();

        // UTC pinnen, damit Wanduhr- und Speicherzeiten in den Assertions
        // identisch sind (der Validator vergleicht in der Anzeige-Zeitzone).
        // Der Config-Fallback greift bei Direktaufrufen ohne Org-Kontext.
        config()->set('app.display_timezone', 'UTC');
        $this->organization = Organization::factory()->create(['timezone' => 'UTC']);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Kernzeit',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'frame_start' => '06:00',
            'frame_end' => '20:00',
            'core_start' => '09:00',
            'core_end' => '15:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function makeEntry(array $attributes = []): TimeEntry {
        return TimeEntry::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => '2030-01-02', // Mittwoch
            'started_at' => '2030-01-02 05:00:00',
            'ended_at' => '2030-01-02 08:00:00',
            'minutes' => 180,
            'break_minutes' => 0,
        ], $attributes));
    }

    public function test_structured_violations_detect_frame_and_core_time(): void {
        $entry = $this->makeEntry();

        $violations = app(CoreTimeValidator::class)->structuredViolations($this->user, $entry);
        $kinds = array_column($violations, 'kind');

        $this->assertContains(CoreTimeValidator::KIND_FRAME_TIME, $kinds);
        $this->assertContains(CoreTimeValidator::KIND_CORE_TIME, $kinds);

        $frame = $violations[array_search(CoreTimeValidator::KIND_FRAME_TIME, $kinds, true)];
        $this->assertSame(60, $frame['value']); // 05:00 → 06:00

        $core = $violations[array_search(CoreTimeValidator::KIND_CORE_TIME, $kinds, true)];
        $this->assertSame(0, $core['value']);       // keine Kernzeit-Abdeckung
        $this->assertSame(360, $core['threshold']); // 09:00–15:00
    }

    public function test_scan_command_records_core_time_findings(): void {
        // Innerhalb des Scan-Fensters (90 Tage): gestern, Beginn vor Rahmenzeit.
        $day = Carbon::now()->subDay()->toDateString();
        $this->makeEntry([
            'date' => $day,
            'started_at' => $day . ' 05:00:00',
            'ended_at' => $day . ' 08:00:00',
        ]);

        $this->artisan('compliance:scan-findings')->assertSuccessful();

        $this->assertTrue(
            ComplianceFinding::query()
                ->where('organization_id', $this->organization->id)
                ->where('rule_code', CoreTimeValidator::KIND_FRAME_TIME)
                ->where('subject_id', $this->user->id)
                ->exists(),
            'Erwarteter frameTime-Befund wurde nicht persistiert.',
        );
    }

    public function test_store_flashes_non_blocking_core_time_warning(): void {
        $response = $this->actingAs($this->user)->post(
            route('projects.time-entries.store', $this->project),
            [
                'date' => '2030-01-02',
                'minutes' => 180,
                'started_at' => '2030-01-02 05:00:00',
                'ended_at' => '2030-01-02 08:00:00',
                'break_minutes' => 0,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning', fn ($value): bool => is_string($value) && $value !== '');

        $this->assertDatabaseCount('time_entries', 1);
    }

    public function test_store_without_violation_has_no_warning(): void {
        $response = $this->actingAs($this->user)->post(
            route('projects.time-entries.store', $this->project),
            [
                'date' => '2030-01-02',
                'minutes' => 330,
                'started_at' => '2030-01-02 09:00:00',
                'ended_at' => '2030-01-02 15:00:00',
                'break_minutes' => 30,
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionMissing('warning');
    }
}
