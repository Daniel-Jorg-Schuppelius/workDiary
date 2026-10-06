<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolAbortTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Patrols;

use App\Enums\Patrol\PatrolRunStatus;
use App\Enums\User\Permission;
use App\Models\Audit\AuditLog;
use App\Models\Classification\EntryType;
use App\Models\Diary\{DiaryEntry, OpenIssue};
use App\Models\Location\LocationDeviceToken;
use App\Models\Patrol\{PatrolRoute, PatrolRun};
use App\Models\Platform\{Organization, User};
use App\Services\Patrol\PatrolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Abbruch eines laufenden Rundgangs (Feature 089): nur mit Begründung, der
 * Lauf zählt nicht als abgeschlossen und nimmt danach nichts mehr an.
 */
final class PatrolAbortTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private PatrolService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->service = app(PatrolService::class);
    }

    public function test_abort_writes_status_reason_actor_and_audit(): void {
        ['run' => $run] = $this->runningPatrol();

        $this->actingAs($this->admin);
        $this->service->abort($run, $this->admin, '  Alarm am Nachbarobjekt  ');

        $run->refresh();
        $this->assertSame(PatrolRunStatus::Aborted, $run->status);
        $this->assertSame('Alarm am Nachbarobjekt', $run->abort_reason);
        $this->assertSame($this->admin->id, $run->aborted_by_user_id);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->deviation_note);

        $log = AuditLog::query()->where('event', 'patrol.aborted')->sole();
        $this->assertSame([$run->id, $this->admin->id, 'Alarm am Nachbarobjekt'], [$log->auditable_id, $log->user_id, $log->changes['reason']]);
        $this->assertSame(__('audit-events.patrol.aborted'), $log->eventLabel());
        $this->assertSame(0, AuditLog::query()->where('event', 'patrol.completed')->count());
    }

    public function test_abort_needs_a_reason(): void {
        ['run' => $run] = $this->runningPatrol();

        try {
            $this->service->abort($run, $this->admin, '   ');
            $this->fail('Ein Abbruch ohne Begründung muss scheitern.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('Der Abbruch braucht eine Begründung.'), $e->getMessage());
        }

        $this->actingAs($this->admin)->post(route('patrols.runs.abort.store', $run), [])->assertSessionHasErrors('reason');
        $this->assertSame(PatrolRunStatus::Running, $run->fresh()?->status);
    }

    /** Abgebrochen und abgeschlossen sind Endzustände — in beide Richtungen. */
    public function test_only_a_running_patrol_can_be_aborted(): void {
        ['run' => $completed, 'tokens' => $tokens] = $this->runningPatrol(1);
        $this->service->scan($completed, $tokens[0]);
        $this->service->complete($completed, $this->admin);
        ['run' => $aborted] = $this->runningPatrol(1);
        $this->service->abort($aborted, $this->admin, 'Alarm');

        $attempts = [
            fn () => $this->service->abort($completed, $this->admin, 'Nachträglich'),
            fn () => $this->service->abort($aborted, $this->admin, 'Noch einmal'),
            fn () => $this->service->complete($aborted, $this->admin, 'Doch fertig'),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Ein beendeter Rundgang wechselt den Zustand nicht mehr.');
            } catch (RuntimeException $e) {
                $this->assertSame(__('Dieser Rundgang läuft nicht mehr.'), $e->getMessage());
            }
        }

        $this->assertSame(PatrolRunStatus::Completed, $completed->fresh()?->status);
        $this->assertNull($completed->fresh()?->abort_reason);
        $this->assertSame('Alarm', $aborted->fresh()?->abort_reason);
    }

    /** Nach dem Abbruch gilt dasselbe wie nach dem Abschluss: keine Scans, die Route ist wieder frei. */
    public function test_aborted_patrol_is_closed_for_scans_like_a_completed_one(): void {
        ['route' => $route, 'run' => $run, 'tokens' => $tokens] = $this->runningPatrol(2);
        $this->service->scan($run, $tokens[0]);
        $this->service->abort($run, $this->admin, 'Alarm');
        $run->refresh();

        try {
            $this->service->scan($run, $tokens[1]);
            $this->fail('Ein abgebrochener Rundgang nimmt keinen Scan mehr an.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('Dieser Rundgang läuft nicht mehr.'), $e->getMessage());
        }
        $this->actingAs($this->admin)->post(route('patrols.runs.scan', $run), ['token' => $tokens[1]])
            ->assertSessionHas('error', __('Dieser Rundgang läuft nicht mehr.'));
        $this->assertSame(1, $run->scans()->count());

        // Das Gerät bekommt für den abgebrochenen Lauf dieselbe Antwort wie für einen abgeschlossenen.
        [, $plain] = LocationDeviceToken::issue($this->admin, 'NFC-Leser Tor 1');
        $afterAbort = $this->postJson("/api/patrol/scan/{$plain}", ['checkpoint' => $tokens[1]]);
        $afterAbort->assertStatus(422)->assertJsonPath('error', 'no_running_patrol');

        $second = $this->service->start($route, $this->admin);
        $this->service->scan($second, $tokens[0]);
        $this->service->scan($second, $tokens[1]);
        $this->service->complete($second, $this->admin);
        $afterCompletion = $this->postJson("/api/patrol/scan/{$plain}", ['checkpoint' => $tokens[1]]);
        $this->assertSame([$afterAbort->status(), $afterAbort->json()], [$afterCompletion->status(), $afterCompletion->json()]);
    }

    /** Offene Kontrollpunkte gehen wie eine Abweichung an die Leitstelle; ins Wachbuch kommt der Lauf nicht. */
    public function test_abort_escalates_open_checkpoints_and_writes_no_logbook_entry(): void {
        EntryType::factory()->create(['organization_id' => $this->organization->id, 'slug' => 'revierfahrt', 'label' => 'Revierfahrt']);
        ['run' => $run, 'tokens' => $tokens] = $this->runningPatrol(2);
        $this->service->scan($run, $tokens[0]);

        $this->service->abort($run, $this->admin, 'Zufahrt gesperrt');

        $issue = OpenIssue::query()->sole();
        $this->assertSame('patrolDeviation', $issue->source_type->value);
        $this->assertSame($run->id, $issue->subject_id);
        $this->assertSame(__('Rundgang „:route" abgebrochen: :missed Kontrollpunkte offen, :late außerhalb des Fensters', ['route' => 'Revierfahrt Nacht', 'missed' => 1, 'late' => 0]), $issue->title);
        $this->assertSame('Zufahrt gesperrt', $issue->description);
        $this->assertSame(0, DiaryEntry::query()->count());

        // Gegenprobe: derselbe Lauf abgeschlossen schreibt den Wachbuch-Eintrag.
        ['run' => $done, 'tokens' => $more] = $this->runningPatrol(1, 'Objektrunde');
        $this->service->scan($done, $more[0]);
        $this->service->complete($done, $this->admin);
        $this->assertSame(1, DiaryEntry::query()->count());
        $this->assertSame(1, OpenIssue::query()->count());
    }

    public function test_abort_dialog_and_action(): void {
        ['route' => $route, 'run' => $run, 'tokens' => $tokens] = $this->runningPatrol(2);
        $this->service->scan($run, $tokens[0]);

        $this->actingAs($this->admin)->get(route('patrols.runs.show', $run))
            ->assertOk()
            ->assertSee(route('patrols.runs.abort.create', $run), false);
        $this->actingAs($this->admin)->get(route('patrols.runs.abort.create', $run))
            ->assertOk()
            ->assertSee(route('patrols.runs.abort.store', $run), false)
            ->assertSee('name="reason"', false);

        $this->actingAs($this->admin)->post(route('patrols.runs.abort.store', $run), ['reason' => 'Alarm am Nachbarobjekt'])
            ->assertRedirect(route('patrols.show', $route))
            ->assertSessionHas('success', __('Rundgang abgebrochen.'));
        $this->assertSame(PatrolRunStatus::Aborted, $run->fresh()?->status);

        // Lauf-Seite: Abzeichen, Grund und Person; weder Scan noch Abschluss noch Abbruch, aber der Bericht.
        $this->actingAs($this->admin)->get(route('patrols.runs.show', $run))
            ->assertOk()
            ->assertSee(PatrolRunStatus::Aborted->label())
            ->assertSee('Alarm am Nachbarobjekt')
            ->assertSee($this->admin->name)
            ->assertSee(__('offen bei Abbruch'))
            ->assertSee('export=pdf', false)
            ->assertDontSee(route('patrols.runs.scan', $run), false)
            ->assertDontSee(route('patrols.runs.complete', $run), false)
            ->assertDontSee(route('patrols.runs.abort.create', $run), false);
        $this->actingAs($this->admin)->get(route('patrols.runs.abort.create', $run))->assertNotFound();

        // Routenseite: der Lauf steht als abgebrochen in der Liste, die Route lässt sich neu starten.
        $this->actingAs($this->admin)->get(route('patrols.show', $route))
            ->assertOk()
            ->assertSee(PatrolRunStatus::Aborted->label());
        $this->actingAs($this->admin)->get(route('patrols.index'))
            ->assertOk()
            ->assertDontSee(route('patrols.runs.show', $run), false);
    }

    /** Wer abschließen darf, darf abbrechen: dispatch.viewAny in der eigenen Organisation. */
    public function test_abort_follows_the_rule_of_completion(): void {
        ['run' => $run] = $this->runningPatrol();
        $guard = User::factory()->create(['organization_id' => $this->organization->id]);
        $outsider = User::factory()->create(['organization_id' => $this->organization->id]);
        $guard->givePermissionTo(Permission::DispatchViewAny->value);

        $this->actingAs($outsider)->get(route('patrols.runs.abort.create', $run))->assertForbidden();
        $this->actingAs($outsider)->post(route('patrols.runs.abort.store', $run), ['reason' => 'Versuch'])->assertForbidden();
        $this->actingAs($outsider)->post(route('patrols.runs.complete', $run), ['deviation_note' => 'Versuch'])->assertForbidden();
        $this->assertSame(PatrolRunStatus::Running, $run->fresh()?->status);

        $this->actingAs($guard)->get(route('patrols.runs.abort.create', $run))->assertOk();
        $this->actingAs($guard)->post(route('patrols.runs.abort.store', $run), ['reason' => 'Alarm'])->assertRedirect();
        $this->assertSame(PatrolRunStatus::Aborted, $run->fresh()?->status);
        $this->assertSame($guard->id, $run->fresh()?->aborted_by_user_id);
    }

    public function test_abort_of_a_foreign_patrol_is_not_found(): void {
        $foreign = Organization::factory()->create();
        $route = PatrolRoute::withoutGlobalScopes()->create(['organization_id' => $foreign->id, 'name' => 'Fremde Route', 'active' => true]);
        $run = PatrolRun::withoutGlobalScopes()->create([
            'organization_id' => $foreign->id, 'patrol_route_id' => $route->id, 'status' => PatrolRunStatus::Running, 'started_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('patrols.runs.abort.create', $run))->assertNotFound();
        $this->actingAs($this->admin)->post(route('patrols.runs.abort.store', $run), ['reason' => 'Versuch'])->assertNotFound();
        $this->assertSame(PatrolRunStatus::Running, PatrolRun::withoutGlobalScopes()->findOrFail($run->id)->status);
    }

    public function test_report_states_the_abort(): void {
        ['run' => $run, 'tokens' => $tokens] = $this->runningPatrol(2);
        $this->service->scan($run, $tokens[0]);
        $this->service->abort($run, $this->admin, 'Alarm am Nachbarobjekt');
        $run->refresh()->load(['route.checkpoints', 'scans', 'starter:id,name', 'abortedBy:id,name']);

        $html = view('pdf.patrol-report', [
            'run' => $run,
            'scans' => $run->scans->keyBy('patrol_checkpoint_id'),
            'missed' => $this->service->missedCheckpoints($run),
        ])->render();
        $this->assertStringContainsString(e(__('Rundgang abgebrochen — nicht abgeschlossen')), $html);
        $this->assertStringContainsString('Alarm am Nachbarobjekt', $html);
        $this->assertStringContainsString(e($this->admin->name), $html);
        $this->assertStringContainsString(e(__('offen bei Abbruch')), $html);
        $this->assertStringNotContainsString(e(__('verpasst')), $html);

        $this->actingAs($this->admin)
            ->get(route('patrols.runs.show', ['patrolRun' => $run, 'export' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @return array{route: PatrolRoute, run: PatrolRun, tokens: list<string>} */
    private function runningPatrol(int $checkpoints = 2, string $name = 'Revierfahrt Nacht'): array {
        $route = PatrolRoute::query()->create([
            'organization_id' => $this->organization->id,
            'name' => $name,
            'active' => true,
            'created_by' => $this->admin->id,
        ]);
        $tokens = [];
        for ($i = 1; $i <= $checkpoints; $i++) {
            $tokens[] = $this->service->addCheckpoint($route, 'Punkt ' . $i, 0, 5)['token'];
        }

        return ['route' => $route, 'run' => $this->service->start($route, $this->admin), 'tokens' => $tokens];
    }
}
