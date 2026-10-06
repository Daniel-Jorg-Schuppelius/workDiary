<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisShelvedCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Enums\Crisis\{CrisisActionStatus, CrisisCaseStatus, CrisisCommunicationStatus, CrisisContinuityImpactStatus};
use App\Enums\User\Permission;
use App\Models\Asset\Asset;
use App\Models\Audit\AuditLog;
use App\Models\Crisis\{CrisisAction, CrisisBusinessProcess, CrisisCase, CrisisCaseLink, CrisisCommunication, CrisisContinuityImpact, CrisisDecision, CrisisMapPoint, CrisisReview, CrisisRole, CrisisSituationReport, CrisisTeamAssignment};
use App\Models\Notification\NotificationDispatchLog;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification, Route};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Schreibschutz der Krisenakte (Entscheidung 2026-10-06): geschlossene und
 * verworfene Akten weisen jede Änderung ab — in der Policy, also auch für
 * Admins —, die Seite zeigt sie nur lesend. Die schreibenden Routen kommen aus
 * dem Router, damit keine neue Aktion an der Prüfung vorbeikommt.
 */
final class CrisisShelvedCaseTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    /** Schreibende crisis.*-Routen ohne Aktenbezug: Anlage, Stabsrollen, BIA-Register, Übungen, Statusseite. */
    private const CASE_FREE = [
        'crisis.store', 'crisis.roles.store',
        'crisis.bia.store', 'crisis.bia.import', 'crisis.bia.update',
        'crisis.exercises.store', 'crisis.exercises.document',
        'crisis.status-page.rotate', 'crisis.status-page.revoke', 'crisis.status-page.toggle',
    ];

    /** Anwesenheit im Krisenraum ist Lese-Telemetrie (90 s, kein Akteninhalt, kein Audit) und bleibt an `view` gebunden. */
    private const READ_BOUND = ['crisis.room.heartbeat'];

    private User $admin;

    private User $second;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-06 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * Akte mit einem Exemplar jedes Unterobjekts, damit jede Route ein Ziel hat.
     *
     * @return array{0: CrisisCase, 1: array<string, mixed>}
     */
    private function file(CrisisCaseStatus $status): array {
        $org = $this->organization->id;
        $case = CrisisCase::query()->create([
            'organization_id' => $org, 'title' => 'Ransomware-Verdacht', 'category' => 'security', 'severity' => 'critical',
            'status' => $status, 'created_by' => $this->admin->id,
            'activated_at' => now()->subDays(3), 'all_clear_at' => now()->subDays(2),
            'closed_at' => $status === CrisisCaseStatus::Closed ? now()->subDay() : null,
        ]);
        $role = CrisisRole::query()->create(['organization_id' => $org, 'name' => 'Leitung', 'active' => true]);
        $fixtures = [
            'role' => $role,
            'assignment' => $case->team()->create(['organization_id' => $org, 'crisis_role_id' => $role->id, 'user_id' => $this->admin->id, 'deputy_user_id' => $this->second->id, 'alerted_at' => now()->subDays(3)]),
            'report' => $case->situationReports()->create(['organization_id' => $org, 'version' => 1, 'content' => 'Erste Lage', 'created_by' => $this->admin->id]),
            'decision' => $case->decisions()->create(['organization_id' => $org, 'decided_at' => now()->subDays(3), 'decision' => 'Netz getrennt', 'decided_by' => $this->admin->id]),
            'action' => $case->actions()->create(['organization_id' => $org, 'title' => 'Backups prüfen', 'priority' => 'high', 'status' => CrisisActionStatus::Open]),
            'draft' => $case->communications()->create(['organization_id' => $org, 'audience' => 'public', 'subject' => 'Entwurf', 'body' => 'Text', 'status' => CrisisCommunicationStatus::Draft, 'created_by' => $this->second->id]),
            'approved' => $case->communications()->create(['organization_id' => $org, 'audience' => 'customers', 'subject' => 'Freigegeben', 'body' => 'Text', 'status' => CrisisCommunicationStatus::Approved, 'created_by' => $this->second->id, 'approved_by' => $this->admin->id, 'approved_at' => now()->subDays(2)]),
            'impact' => $case->continuityImpacts()->create(['organization_id' => $org, 'process_name' => 'Auftragsannahme', 'status' => CrisisContinuityImpactStatus::Down]),
            'point' => CrisisMapPoint::query()->create(['organization_id' => $org, 'crisis_case_id' => $case->id, 'label' => 'Sammelpunkt', 'kind' => 'assembly', 'lat' => '50.94', 'lng' => '6.95', 'created_by' => $this->admin->id]),
            'process' => CrisisBusinessProcess::query()->create(['organization_id' => $org, 'name' => 'Lager', 'criticality' => 'high', 'rto_hours' => 4]),
            'asset' => Asset::factory()->create(['organization_id' => $org, 'name' => 'Pumpwerk Süd', 'location_lat' => '50.9375000', 'location_lng' => '6.9603000']),
        ];
        if ($status->isShelved()) {
            $case->review()->create(['organization_id' => $org, 'summary' => 'Verlauf und Lehren', 'reviewed_by' => $this->admin->id, 'reviewed_at' => now()->subDays(2)]);
        }

        return [$case, $fixtures];
    }

    /**
     * Jede schreibende Aktion an der Akte mit gültigen Eingaben — die Reihenfolge
     * trägt den offenen Durchlauf (Quittieren vor Entfernen, Nachbereitung nach Entwarnung).
     *
     * @param  array<string, mixed>  $f
     * @return array<string, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function requests(CrisisCase $case, array $f): array {
        return [
            'crisis.status' => ['post', route('crisis.status', $case), ['status' => 'in_progress']],
            'crisis.activate' => ['post', route('crisis.activate', $case), []],
            'crisis.team.store' => ['post', route('crisis.team.store', $case), ['crisis_role_id' => $f['role']->id, 'user_id' => $this->second->id]],
            'crisis.alert' => ['post', route('crisis.alert', $case), []],
            'crisis.alert.escalate' => ['post', route('crisis.alert.escalate', $case), []],
            'crisis.team.acknowledge' => ['post', route('crisis.team.acknowledge', [$case, $f['assignment']]), []],
            'crisis.team.destroy' => ['delete', route('crisis.team.destroy', [$case, $f['assignment']]), []],
            'crisis.sitrep.store' => ['post', route('crisis.sitrep.store', $case), ['content' => 'Neue Lage']],
            'crisis.decisions.store' => ['post', route('crisis.decisions.store', $case), ['decision' => 'Wiederanlauf freigeben']],
            'crisis.actions.store' => ['post', route('crisis.actions.store', $case), ['title' => 'Forensik beauftragen', 'priority' => 'high']],
            'crisis.actions.update' => ['put', route('crisis.actions.update', [$case, $f['action']]), ['status' => 'done']],
            'crisis.communications.store' => ['post', route('crisis.communications.store', $case), ['audience' => 'public', 'subject' => 'Störung', 'body' => 'Wir arbeiten daran.']],
            'crisis.communications.approve' => ['post', route('crisis.communications.approve', [$case, $f['draft']]), []],
            'crisis.communications.sent' => ['post', route('crisis.communications.sent', [$case, $f['approved']]), ['channel' => 'Mail']],
            'crisis.bcm.store' => ['post', route('crisis.bcm.store', $case), ['process_name' => 'Versand']],
            'crisis.bcm.adopt' => ['post', route('crisis.bcm.adopt', $case), ['process_id' => $f['process']->sqid]],
            'crisis.bcm.update' => ['put', route('crisis.bcm.update', [$case, $f['impact']]), ['status' => 'restored']],
            'crisis.links.store' => ['post', route('crisis.links.store', $case), ['linkable_type' => 'asset', 'linkable_sqid' => $f['asset']->sqid]],
            'crisis.room.points.store' => ['post', route('crisis.room.points.store', $case), ['label' => 'Sperrung B9', 'kind' => 'closure', 'lat' => '50.95', 'lng' => '6.96']],
            'crisis.room.points.destroy' => ['delete', route('crisis.room.points.destroy', $f['point']), []],
            'crisis.all-clear' => ['post', route('crisis.all-clear', $case), []],
            'crisis.review.store' => ['post', route('crisis.review.store', $case), ['summary' => 'Verlauf']],
            'crisis.close' => ['post', route('crisis.close', $case), []],
        ];
    }

    /** @return array<string, mixed> */
    private function snapshot(): array {
        $rows = [];
        foreach ([CrisisCase::class, CrisisTeamAssignment::class, CrisisSituationReport::class, CrisisDecision::class, CrisisAction::class, CrisisCommunication::class, CrisisContinuityImpact::class, CrisisCaseLink::class, CrisisReview::class, CrisisMapPoint::class, CrisisRole::class] as $model) {
            $rows[$model] = $model::query()->orderBy('id')->get()->toArray();
        }
        $rows['audit_logs'] = AuditLog::query()->count();
        $rows['notification_dispatch_logs'] = NotificationDispatchLog::query()->count();

        return $rows;
    }

    public function test_every_writing_crisis_route_is_either_case_bound_and_listed_or_declared_case_free(): void {
        $writing = [];
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (str_starts_with($name, 'crisis.') && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== []) {
                $writing[] = $name;
            }
        }
        sort($writing);

        [$case, $fixtures] = $this->file(CrisisCaseStatus::Closed);
        $covered = [...array_keys($this->requests($case, $fixtures)), ...self::CASE_FREE, ...self::READ_BOUND];
        sort($covered);

        $this->assertSame($writing, $covered, 'Jede schreibende Krisen-Route braucht einen Fall in dieser Prüfung.');
    }

    public function test_closed_file_refuses_every_change_even_for_admins(): void {
        Notification::fake();
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Closed);
        $before = $this->snapshot();

        foreach ($this->requests($case, $fixtures) as $name => [$method, $url, $payload]) {
            $this->actingAs($this->admin)->{$method}($url, $payload)->assertForbidden();
            $this->assertSame($before, $this->snapshot(), $name);
        }

        $this->assertSame(CrisisCaseStatus::Closed, $case->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_closed_file_refuses_changes_from_crisis_managers_too(): void {
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Closed);
        $manager = $this->actorIn($this->organization, [Permission::CrisisManage, Permission::CrisisApprove]);
        $before = $this->snapshot();

        foreach (['crisis.sitrep.store', 'crisis.activate', 'crisis.actions.update', 'crisis.team.acknowledge'] as $name) {
            [$method, $url, $payload] = $this->requests($case, $fixtures)[$name];
            $this->actingAs($manager)->{$method}($url, $payload)->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_discarded_file_refuses_changes_as_well(): void {
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Discarded);
        $before = $this->snapshot();

        foreach (['crisis.status', 'crisis.sitrep.store', 'crisis.actions.update', 'crisis.communications.approve', 'crisis.room.points.destroy'] as $name) {
            [$method, $url, $payload] = $this->requests($case, $fixtures)[$name];
            $this->actingAs($this->admin)->{$method}($url, $payload)->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(CrisisCaseStatus::Discarded, $case->fresh()->status);
    }

    public function test_open_file_still_takes_every_change(): void {
        Notification::fake();
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Activated);
        $before = $this->snapshot();

        foreach ($this->requests($case, $fixtures) as $name => [$method, $url, $payload]) {
            $response = $this->actingAs($this->admin)->{$method}($url, $payload);
            $this->assertNotSame(403, $response->status(), $name);
            $response->assertRedirect();
        }

        $this->assertNotSame($before, $this->snapshot());
        $this->assertSame(2, $case->situationReports()->count());
        $this->assertSame(CrisisActionStatus::Done, $fixtures['action']->fresh()->status);
        $this->assertSame(CrisisCommunicationStatus::Sent, $fixtures['approved']->fresh()->status);

        // Der Durchlauf endet auf dem fachlichen Weg (Entwarnung → Nachbereitung → Abschluss) — ab da ist Schluss.
        $this->assertSame(CrisisCaseStatus::Closed, $case->fresh()->status);
        $this->assertNotNull($case->fresh()->closed_at);
        $closed = $this->snapshot();
        $this->actingAs($this->admin)->post(route('crisis.sitrep.store', $case), ['content' => 'Zu spät'])->assertForbidden();
        $this->assertSame($closed, $this->snapshot());
    }

    public function test_reading_and_room_presence_stay_open_on_a_closed_file(): void {
        [$case] = $this->file(CrisisCaseStatus::Closed);

        $this->actingAs($this->admin)->get(route('crisis.show', $case))->assertOk();
        $this->actingAs($this->admin)->postJson(route('crisis.room.heartbeat', $case))->assertOk()->assertJsonPath('present.0.name', $this->admin->name);
    }

    public function test_closed_file_page_shows_content_but_no_forms(): void {
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Closed);

        $response = $this->actingAs($this->admin)->get(route('crisis.show', $case))->assertOk()
            ->assertSeeText(__('Akte geschlossen am :date — nur lesend.', ['date' => $case->closed_at->fdatetime()]))
            ->assertSeeText('Erste Lage')
            ->assertSeeText('Netz getrennt')
            ->assertSeeText('Backups prüfen')
            ->assertSeeText('Verlauf und Lehren')
            ->assertSeeText('Sammelpunkt');
        $html = (string) $response->getContent();

        foreach ($this->requests($case, $fixtures) as $name => [, $url]) {
            $this->assertFalse(str_contains($html, 'action="' . $url . '"'), "Formular für {$name} auf der geschlossenen Akte.");
        }
        $this->assertFalse(str_contains($html, 'action="' . route('crisis.roles.store') . '"'));
        // Weder Lagezustand noch Maßnahmen- oder Wiederanlaufstatus lassen sich noch wählen.
        $this->assertFalse(str_contains($html, '<select name="status"'));
        $this->assertFalse(str_contains($html, e((string) __('Nachbereitung wird nach der Entwarnung möglich.'))));
    }

    public function test_discarded_file_page_names_its_state(): void {
        [$case] = $this->file(CrisisCaseStatus::Discarded);
        $case->review()->delete();

        $this->actingAs($this->admin)->get(route('crisis.show', $case))->assertOk()
            ->assertSeeText(__('Akte verworfen — nur lesend.'))
            ->assertSeeText(__('Keine Nachbereitung dokumentiert.'))
            ->assertDontSeeText(__('Nachbereitung wird nach der Entwarnung möglich.'));
    }

    public function test_open_file_page_keeps_its_forms_and_no_read_only_notice(): void {
        [$case, $fixtures] = $this->file(CrisisCaseStatus::Activated);

        $response = $this->actingAs($this->admin)->get(route('crisis.show', $case))->assertOk()
            ->assertDontSeeText('— nur lesend.');
        $html = (string) $response->getContent();

        foreach (['crisis.sitrep.store', 'crisis.actions.store', 'crisis.team.store', 'crisis.links.store', 'crisis.room.points.store'] as $name) {
            $this->assertTrue(str_contains($html, 'action="' . $this->requests($case, $fixtures)[$name][1] . '"'), $name);
        }
    }
}
