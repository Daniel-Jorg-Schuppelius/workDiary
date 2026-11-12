<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolMaintenanceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Patrols;

use App\Models\Diary\OpenIssue;
use App\Models\Patrol\{PatrolCheckpoint, PatrolRoute};
use App\Models\Platform\{Team, User};
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Patrol\DeadlineScans\PatrolOverdueScan;
use App\Services\Patrol\{PatrolReport, PatrolService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1611: Routen und Kontrollpunkte pflegen und deaktivieren, ohne frühere
 * Läufe zu verfälschen; Abweichungen sofort an die Verantwortliche; nicht
 * beendete Rundgänge über den Fristenlauf.
 */
final class PatrolMaintenanceTest extends TestCase {
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

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{route: PatrolRoute, tokens: list<string>} */
    private function route(int $checkpoints = 2, ?User $responsible = null): array {
        $route = PatrolRoute::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Revierfahrt Nacht',
            'responsible_user_id' => $responsible?->id,
            'active' => true,
            'created_by' => $this->admin->id,
        ]);
        $tokens = [];
        for ($i = 1; $i <= $checkpoints; $i++) {
            $tokens[] = $this->service->addCheckpoint($route, 'Punkt ' . $i, ($i - 1) * 10, 5)['token'];
        }

        return ['route' => $route, 'tokens' => $tokens];
    }

    public function test_route_is_edited_and_deactivated_over_http(): void {
        ['route' => $route] = $this->route(1);
        $lead = User::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Lea Leitstelle']);

        $this->actingAs($this->admin)->get(route('patrols.edit', $route))->assertOk()->assertSee('Revierfahrt Nacht');
        $this->actingAs($this->admin)
            ->put(route('patrols.update', $route), ['name' => 'Revierfahrt Tag', 'responsible' => $lead->sqid])
            ->assertRedirect(route('patrols.show', $route));
        $route->refresh();
        $this->assertSame('Revierfahrt Tag', $route->name);
        $this->assertSame($lead->id, $route->responsible_user_id);

        $this->actingAs($this->admin)->get(route('patrols.show', $route))->assertOk()->assertSee('Lea Leitstelle')->assertSee(route('patrols.edit', $route), false);
        $this->actingAs($this->admin)->post(route('patrols.toggle', $route))->assertRedirect();
        $this->assertFalse($route->refresh()->active);
        $this->actingAs($this->admin)->get(route('patrols.index'))->assertOk()->assertDontSee(route('patrols.start', $route), false);
        $this->expectException(\RuntimeException::class);
        $this->service->start($route, $this->admin);
    }

    public function test_deactivated_checkpoint_keeps_earlier_runs_unchanged(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(2);
        $checkpoint = PatrolCheckpoint::query()->where('patrol_route_id', $route->id)->orderBy('position')->get()->last();

        Carbon::setTestNow(now()->addMinute());
        $earlier = $this->service->start($route, $this->admin);
        $this->service->scan($earlier, $tokens[0]);
        $this->service->complete($earlier, $this->admin, 'Punkt 2 unzugänglich');

        Carbon::setTestNow(now()->addMinutes(5));
        $this->actingAs($this->admin)
            ->post(route('patrols.checkpoints.deactivate', [$route, $checkpoint]))
            ->assertRedirect();
        $this->assertNotNull($checkpoint->refresh()->deactivated_at);
        $this->actingAs($this->admin)->get(route('patrols.show', $route))->assertOk()->assertSee(__('patrol.checkpoint.inactive_badge'));

        // Früherer Lauf: der Punkt bleibt verpasst.
        $this->assertSame([$checkpoint->id], $this->service->missedCheckpoints($earlier)->pluck('id')->all());

        // Neuer Lauf: der Punkt wird nicht mehr erwartet und ist nicht scanbar.
        Carbon::setTestNow(now()->addMinute());
        $later = $this->service->start($route, $this->admin);
        $this->service->scan($later, $tokens[0]);
        $this->assertSame([], $this->service->missedCheckpoints($later)->pluck('id')->all());
        try {
            $this->service->scan($later, $tokens[1]);
            $this->fail('Ein deaktivierter Kontrollpunkt wurde angenommen.');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('patrol.checkpoint.deactivated'), $e->getMessage());
        }

        $report = app(PatrolReport::class)->build((int) $this->organization->id, CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addDay());
        $this->assertSame([2, 1], array_map(fn (array $row): int => $row['expected'], $report['rows']));
    }

    public function test_checkpoint_edit_dialog_and_update(): void {
        ['route' => $route] = $this->route(1);
        $checkpoint = PatrolCheckpoint::query()->where('patrol_route_id', $route->id)->firstOrFail();

        $this->actingAs($this->admin)->get(route('patrols.checkpoints.edit', [$route, $checkpoint]))->assertOk()->assertSee('Punkt 1');
        $this->actingAs($this->admin)
            ->put(route('patrols.checkpoints.update', [$route, $checkpoint]), ['label' => 'Tor Nord', 'expected_offset_minutes' => 3, 'tolerance_minutes' => 7])
            ->assertRedirect(route('patrols.show', $route));
        $checkpoint->refresh();
        $this->assertSame(['Tor Nord', 3, 7], [$checkpoint->label, $checkpoint->expected_offset_minutes, $checkpoint->tolerance_minutes]);
    }

    public function test_late_scan_raises_the_issue_immediately_for_the_responsible(): void {
        $responsible = User::factory()->create(['organization_id' => $this->organization->id]);
        ['route' => $route, 'tokens' => $tokens] = $this->route(2, $responsible);
        $run = $this->service->start($route, $this->admin);

        Carbon::setTestNow(now()->addMinutes(30));
        $this->service->scan($run, $tokens[0]);

        $issue = OpenIssue::query()->sole();
        $this->assertSame($responsible->id, $issue->assignee_user_id);
        $this->assertSame(__('patrol.deviation.running_title', ['route' => 'Revierfahrt Nacht']), $issue->title);

        // Der Abschluss schreibt denselben Punkt fort statt einen zweiten anzulegen.
        $this->service->complete($run, $this->admin, 'Stau');
        $this->assertSame(1, OpenIssue::query()->count());
        $this->assertSame('Stau', OpenIssue::query()->sole()->description);
    }

    public function test_without_responsible_the_team_lead_of_the_walker_gets_the_issue(): void {
        $teamLead = User::factory()->create(['organization_id' => $this->organization->id]);
        $walker = User::factory()->create(['organization_id' => $this->organization->id]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'lead_user_id' => $teamLead->id]);
        $walker->teams()->attach($team->id);
        ['route' => $route, 'tokens' => $tokens] = $this->route(1);
        $run = $this->service->start($route, $walker);

        Carbon::setTestNow(now()->addMinutes(20));
        $this->service->scan($run, $tokens[0]);

        $this->assertSame($teamLead->id, OpenIssue::query()->sole()->assignee_user_id);
    }

    public function test_unfinished_patrol_is_flagged_once_after_planned_duration(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(2);
        $run = $this->service->start($route, $this->admin);
        $scan = app(PatrolOverdueScan::class);
        $options = new DeadlineScanOptions(7, 30);

        // Soll-Dauer: Punkt 2 (+10) plus Toleranz (5) = 15 Minuten.
        Carbon::setTestNow(now()->addMinutes(14));
        $scan->run(app(NotificationDispatcher::class), $options);
        $this->assertSame(0, OpenIssue::query()->count());

        Carbon::setTestNow(now()->addMinutes(2));
        $scan->run(app(NotificationDispatcher::class), $options);
        $issue = OpenIssue::query()->sole();
        $this->assertSame(__('patrol.deviation.overdue_title', ['route' => 'Revierfahrt Nacht']), $issue->title);
        $this->assertNotNull($run->refresh()->overdue_flagged_at);

        $scan->run(app(NotificationDispatcher::class), $options);
        $this->assertSame(1, OpenIssue::query()->count());

        // Die Leitstelle kann den Rundgang danach abbrechen.
        $this->service->abort($run, $this->admin, 'Wächter nicht erreichbar');
        $this->assertSame(1, OpenIssue::query()->count());
    }
}
