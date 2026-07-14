<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingPersistenceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Reporting;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Compliance\ComplianceFindingStatus;
use App\Models\{Attendance, ComplianceFinding, Organization, User};
use App\Services\Compliance\AttendanceComplianceChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Persistenz + Acknowledge-Workflow der Compliance-Verstöße (Feature 006,
 * Welle D): Dedup beim erneuten Scan, Auto-„behoben" (kein Hard-Delete),
 * Quittieren/Akzeptieren mit auditiertem Statuswechsel, Recht + Org-Isolation.
 */
class ComplianceFindingPersistenceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $user;

    private string $day = '2026-06-05';

    protected function setUp(): void {
        parent::setUp();
        // Fixe „Jetzt", damit das Scan-Fenster (now − Tage … now) die
        // Test-Stempeltage stabil einschließt.
        $this->travelTo(Carbon::parse('2026-06-10 12:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        config(['timesheet.breaks.auto_apply' => false]);

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function dailyViolation(?int $userId = null, ?string $date = null, int $break = 50): Attendance {
        // 06:00–17:00 = 11 h brutto − 50 min = 10:10 netto > 10 h (nur
        // Tageshöchstarbeitszeit; 50 ≥ 45 Pflichtpause bei 11 h ⇒ kein Pausenverstoß).
        $date ??= $this->day;

        return Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $userId ?? $this->user->id,
            'date' => $date,
            'started_at' => "$date 06:00:00",
            'ended_at' => "$date 17:00:00",
            'break_minutes_auto' => 0,
            'break_minutes_manual' => $break,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);
    }

    private function scan(int $days = 90): void {
        $this->artisan('compliance:scan-findings', ['--days' => $days])->assertExitCode(0);
    }

    public function test_scan_persists_a_finding(): void {
        $this->dailyViolation();

        $this->scan();

        $this->assertSame(1, ComplianceFinding::query()->count());
        $finding = ComplianceFinding::query()->first();
        $this->assertNotNull($finding);
        $this->assertSame(AttendanceComplianceChecker::KIND_MAX_DAILY_HOURS, $finding->rule_code);
        $this->assertSame('arbzg', $finding->category);
        $this->assertSame(ComplianceFindingStatus::Open, $finding->status);
        $this->assertSame($this->user->id, $finding->subject_id);
        $this->assertSame(User::class, $finding->subject_type);
        $this->assertSame(610, $finding->detected_value);
        $this->assertSame(600, $finding->threshold_value);
    }

    public function test_second_scan_does_not_duplicate(): void {
        $this->dailyViolation();

        $this->scan();
        $this->scan();

        $this->assertSame(1, ComplianceFinding::query()->count());
    }

    public function test_detection_writes_audit_event(): void {
        $this->dailyViolation();

        $this->scan();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'compliance.finding.detected',
            'auditable_type' => ComplianceFinding::class,
        ]);
    }

    public function test_vanished_violation_is_resolved_not_deleted(): void {
        $attendance = $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        // Verstoß verschwindet (genügend Pause ⇒ netto ≤ 10 h) → erneuter Scan.
        $attendance->update(['break_minutes_manual' => 90]);
        $this->scan();

        $finding->refresh();
        $this->assertSame(ComplianceFindingStatus::Resolved, $finding->status);
        $this->assertNotNull($finding->resolved_at);
        // Revisionssicher: Zeile bleibt bestehen, kein Hard-/Soft-Delete.
        $this->assertDatabaseHas('compliance_findings', ['id' => $finding->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'compliance.finding.resolved',
            'auditable_id' => $finding->id,
        ]);
    }

    public function test_reappearing_violation_is_reopened(): void {
        $attendance = $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $attendance->update(['break_minutes_manual' => 90]);
        $this->scan();
        $this->assertSame(ComplianceFindingStatus::Resolved, $finding->fresh()?->status);

        // Gleicher Verstoß tritt wieder auf → derselbe Datensatz wird reaktiviert.
        $attendance->update(['break_minutes_manual' => 50]);
        $this->scan();

        $this->assertSame(1, ComplianceFinding::query()->count());
        $this->assertSame(ComplianceFindingStatus::Open, $finding->fresh()?->status);
        $this->assertNull($finding->fresh()?->resolved_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'compliance.finding.reopened',
            'auditable_id' => $finding->id,
        ]);
    }

    public function test_admin_can_acknowledge_without_note(): void {
        $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
            ])
            ->assertRedirect(route('reports.compliance.history'));

        $finding->refresh();
        $this->assertSame(ComplianceFindingStatus::Acknowledged, $finding->status);
        $this->assertSame($this->admin->id, $finding->acknowledged_by);
        $this->assertNotNull($finding->acknowledged_at);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'compliance.finding.acknowledged',
            'auditable_id' => $finding->id,
        ]);
    }

    public function test_accept_requires_note(): void {
        $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Accepted->value,
            ])
            ->assertSessionHasErrors('acknowledge_note');

        $this->assertSame(ComplianceFindingStatus::Open, $finding->fresh()?->status);
    }

    public function test_accept_with_note_sets_status_and_note(): void {
        $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Accepted->value,
                'note' => 'Einmaliger Sondereinsatz, betriebsrätlich abgestimmt.',
            ])
            ->assertRedirect(route('reports.compliance.history'));

        $finding->refresh();
        $this->assertSame(ComplianceFindingStatus::Accepted, $finding->status);
        $this->assertSame('Einmaliger Sondereinsatz, betriebsrätlich abgestimmt.', $finding->acknowledge_note);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'compliance.finding.accepted',
            'auditable_id' => $finding->id,
        ]);
    }

    public function test_status_change_keeps_audit_hash_chain_valid(): void {
        $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
                'note' => 'gesichtet',
            ])->assertRedirect();

        $this->artisan('audit:verify')->assertExitCode(0);
    }

    public function test_resolved_finding_cannot_be_acknowledged(): void {
        $attendance = $this->dailyViolation();
        $this->scan();
        $attendance->update(['break_minutes_manual' => 90]);
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();
        $this->assertSame(ComplianceFindingStatus::Resolved, $finding->status);

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ComplianceFindingStatus::Resolved, $finding->fresh()?->status);
    }

    public function test_history_is_gated_by_compliance_permission(): void {
        $this->actingAs($this->user)
            ->get(route('reports.compliance.history'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('reports.compliance.history'))
            ->assertOk();
    }

    public function test_plain_user_cannot_acknowledge(): void {
        $this->dailyViolation();
        $this->scan();
        $finding = ComplianceFinding::query()->firstOrFail();

        $this->actingAs($this->user)
            ->post(route('reports.compliance.acknowledge', $finding), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
            ])
            ->assertForbidden();

        $this->assertSame(ComplianceFindingStatus::Open, $finding->fresh()?->status);
    }

    public function test_history_status_filter(): void {
        $this->dailyViolation();
        $this->dailyViolation(date: '2026-06-06');
        $this->scan();
        $first = ComplianceFinding::query()->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $first), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
                'note' => 'gesichtet',
            ])->assertRedirect();

        // Filter auf einen Status ohne Treffer ⇒ Leerzustand.
        $this->actingAs($this->admin)
            ->get(route('reports.compliance.history', ['status' => ComplianceFindingStatus::Resolved->value]))
            ->assertOk()
            ->assertSee((string) __('compliance.history.empty'));

        // Ohne Filter sind beide Verstöße sichtbar (2 Zeilen, quittiert-Notiz).
        $this->actingAs($this->admin)
            ->get(route('reports.compliance.history'))
            ->assertOk()
            ->assertSee('gesichtet');
    }

    public function test_org_isolation(): void {
        // Verstoß in einer Fremd-Organisation.
        $otherOrg = Organization::factory()->create(['timezone' => 'UTC']);
        $otherUser = User::factory()->user()->create(['organization_id' => $otherOrg->id]);
        Attendance::factory()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $otherUser->id,
            'date' => $this->day,
            'started_at' => "{$this->day} 06:00:00",
            'ended_at' => "{$this->day} 17:00:00",
            'break_minutes_auto' => 0,
            'break_minutes_manual' => 50,
            'status' => AttendanceStatus::Closed->value,
            'source' => AttendanceSource::Manual->value,
        ]);
        // Eigener Verstoß.
        $this->dailyViolation();

        $this->scan();

        // Fremd-Finding existiert, ist aber in der eigenen Org-Sicht unsichtbar.
        app()->instance('currentOrganization', $otherOrg);
        $foreign = ComplianceFinding::query()->where('subject_id', $otherUser->id)->firstOrFail();
        app()->instance('currentOrganization', $this->organization);

        $this->actingAs($this->admin)
            ->get(route('reports.compliance.history'))
            ->assertOk()
            ->assertDontSee($otherUser->name);

        // Acknowledge eines Fremd-Findings ⇒ 404 (Sqid-Bindung org-gescopt).
        $this->actingAs($this->admin)
            ->post(route('reports.compliance.acknowledge', $foreign), [
                'status' => ComplianceFindingStatus::Acknowledged->value,
            ])
            ->assertNotFound();
    }
}
