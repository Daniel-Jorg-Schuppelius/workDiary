<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionSelfApplyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Attendance\AttendanceSource;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Enums\User\Permission as P;
use App\Models\{Attendance, Organization, TimeCorrectionRequest, User};
use App\Services\TimeApproval\TimeCorrectionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Selbstkorrektur-Modus für vergessene Stempelungen: per Org-Einstellung trägt
 * der Mitarbeiter direkt nach (Manual, self_applied); sonst Pflicht-Genehmigung.
 */
class TimeCorrectionSelfApplyTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function submittedSelfCorrection(Organization $org, User $emp, ?User $requestedBy = null): TimeCorrectionRequest {
        $service = app(TimeCorrectionService::class);
        $req = $service->createDraft(
            $emp,
            CarbonImmutable::parse('2026-06-01'),
            'Stempelung am 01.06. vergessen einzutragen.',
            [[
                'target_type' => Attendance::class,
                'target_id' => null,
                'action' => 'create',
                'before' => null,
                'after' => [
                    'organization_id' => $org->id,
                    'user_id' => $emp->id,
                    'date' => '2026-06-01',
                    'started_at' => '2026-06-01 08:00:00',
                    'ended_at' => '2026-06-01 16:00:00',
                    'duration_minutes' => 480,
                    'status' => 'closed',
                    'source' => 'clock', // wird beim Anwenden auf Manual erzwungen
                ],
            ]],
            $requestedBy ?? $emp,
        );

        return $service->submit($req, $emp);
    }

    public function test_self_apply_when_org_enables_it(): void {
        $org = Organization::factory()->create(['settings' => ['attendance' => ['self_correction' => 'self']]]);
        $emp = User::factory()->create(['organization_id' => $org->id]);
        $service = app(TimeCorrectionService::class);

        $req = $this->submittedSelfCorrection($org, $emp);
        $this->assertTrue($service->selfApplicable($req));
        $service->selfApply($req);

        $req->refresh();
        $this->assertSame(TimeCorrectionStatus::Applied, $req->status);
        $this->assertTrue($req->self_applied);

        $att = Attendance::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame(AttendanceSource::Manual, $att->source, 'Nachgetragene Stempelung ist Manual.');

        // Vollaudit 2026-07 (M2): explizites Audit-Event mit Antragsreferenz —
        // per Antrag angewandte Änderungen bleiben von Direktbearbeitung
        // unterscheidbar (zeit-korrekturen.md §3.3).
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'attendance.correctedByApproval',
            'auditable_type' => Attendance::class,
            'auditable_id' => $att->id,
        ]);
        $log = \App\Models\AuditLog::query()->where('event', 'attendance.correctedByApproval')->firstOrFail();
        $this->assertSame((int) $req->id, (int) $log->changes['correction_request_id']);
    }

    public function test_requires_approval_by_default(): void {
        $org = Organization::factory()->create(); // ohne self-Setting → 'request'
        $emp = User::factory()->create(['organization_id' => $org->id]);
        $service = app(TimeCorrectionService::class);

        $req = $this->submittedSelfCorrection($org, $emp);

        $this->assertFalse($service->selfApplicable($req));
        $this->assertSame(TimeCorrectionStatus::Submitted, $req->status);
        $this->assertSame(0, Attendance::where('user_id', $emp->id)->count());
    }

    public function test_submit_endpoint_self_applies_in_self_mode(): void {
        $org = Organization::factory()->create(['settings' => ['attendance' => ['self_correction' => 'self']]]);
        $emp = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        SpatiePermission::findOrCreate(P::CorrectionSubmitOwn->value, 'web');
        $emp->givePermissionTo(P::CorrectionSubmitOwn->value);
        // Draft anlegen, dann ueber den Controller einreichen.
        $draft = app(TimeCorrectionService::class)->createDraft(
            $emp,
            CarbonImmutable::parse('2026-06-01'),
            'Stempelung am 01.06. vergessen einzutragen.',
            [[
                'target_type' => Attendance::class, 'target_id' => null, 'action' => 'create', 'before' => null,
                'after' => ['organization_id' => $org->id, 'user_id' => $emp->id, 'date' => '2026-06-01',
                    'started_at' => '2026-06-01 08:00:00', 'ended_at' => '2026-06-01 16:00:00',
                    'duration_minutes' => 480, 'status' => 'closed'],
            ]],
            $emp,
        );

        $this->actingAs($emp)->post(route('corrections.submit', $draft))->assertRedirect();

        $draft->refresh();
        $this->assertSame(TimeCorrectionStatus::Applied, $draft->status);
        $this->assertTrue($draft->self_applied);
        $this->assertSame(AttendanceSource::Manual, Attendance::where('user_id', $emp->id)->firstOrFail()->source);
    }

    public function test_on_behalf_request_is_not_self_applied(): void {
        $org = Organization::factory()->create(['settings' => ['attendance' => ['self_correction' => 'self']]]);
        $emp = User::factory()->create(['organization_id' => $org->id]);
        $hr = User::factory()->create(['organization_id' => $org->id]);
        $service = app(TimeCorrectionService::class);

        $req = $this->submittedSelfCorrection($org, $emp, requestedBy: $hr);

        $this->assertFalse($service->selfApplicable($req), 'Im Namen eines anderen → immer Genehmigung.');
    }
}
