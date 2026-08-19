<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Patrols;

use App\Models\{OpenIssue, User};
use App\Models\Patrol\{PatrolRoute, PatrolRun};
use App\Services\Patrol\PatrolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wächterrundgänge (Feature 089, MVP-663–665).
 *
 * Kern der Prüfung: **Der Scan belegt Punkt und Zeit gegen das Soll-Fenster**,
 * Abweichungen erzwingen eine Begründung und eskalieren als offener Punkt,
 * und ein verlorener Tag ist ohne Routenneuaufbau ersetzbar.
 */
final class PatrolTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @return array{route: PatrolRoute, tokens: list<string>} */
    private function route(int $checkpoints = 2): array {
        $route = PatrolRoute::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Revierfahrt Nacht',
            'active' => true,
            'created_by' => $this->admin->id,
        ]);
        $service = app(PatrolService::class);
        $tokens = [];
        for ($i = 1; $i <= $checkpoints; $i++) {
            $issued = $service->addCheckpoint($route, 'Punkt ' . $i, ($i - 1) * 10, 5);
            $tokens[] = $issued['token'];
        }

        return ['route' => $route, 'tokens' => $tokens];
    }

    public function test_scan_resolves_token_and_rates_the_window(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route();
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);

        // Punkt 1 (Soll +0 ± 5) sofort gescannt → im Fenster.
        $service->scan($run, $tokens[0]);
        $scan = $run->scans()->firstOrFail();
        $this->assertTrue($scan->in_window);

        // Unbekannter Token wird abgewiesen.
        $this->expectException(\RuntimeException::class);
        $service->scan($run, 'FALSCHER-TOKEN');
    }

    /** Doppelscan zählt einmal. */
    public function test_double_scan_is_idempotent(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(1);
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);

        $service->scan($run, $tokens[0]);
        $service->scan($run, $tokens[0]);

        $this->assertSame(1, $run->scans()->count());
    }

    /** Abweichung: Abschluss ohne Begründung wird abgewiesen … */
    public function test_completion_with_missed_checkpoints_requires_justification(): void {
        ['route' => $route] = $this->route();
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);

        $this->expectException(\RuntimeException::class);
        $service->complete($run, $this->admin);
    }

    /** … und MIT Begründung entsteht der offene Punkt an der Leitstelle. */
    public function test_deviation_raises_an_open_issue(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(2);
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);
        $service->scan($run, $tokens[0]); // Punkt 2 bleibt verpasst.

        $service->complete($run, $this->admin, 'Zufahrt blockiert, Punkt 2 nicht erreichbar');

        $this->assertSame(PatrolRun::STATUS_COMPLETED, $run->fresh()?->status);
        $issue = OpenIssue::query()->firstOrFail();
        $this->assertSame('patrolDeviation', $issue->source_type->value);
        $this->assertStringContainsString('Revierfahrt Nacht', $issue->title);
    }

    /** Ohne Abweichung: kein offener Punkt, keine Begründungspflicht. */
    public function test_clean_run_completes_without_issue(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(1);
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);
        $service->scan($run, $tokens[0]);

        $service->complete($run, $this->admin);

        $this->assertSame(0, OpenIssue::query()->count());
    }

    /** Verlorener Tag: neuer Token ersetzt den alten, Route bleibt. */
    public function test_token_reissue_invalidates_the_old_tag(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route(1);
        $service = app(PatrolService::class);
        $checkpoint = $route->checkpoints()->firstOrFail();

        $reissued = $service->reissueToken($checkpoint);
        $run = $service->start($route, $this->admin);

        // Der alte Token ist wertlos …
        try {
            $service->scan($run, $tokens[0]);
            $this->fail('Alter Token darf nicht mehr auflösen.');
        } catch (\RuntimeException) {
        }

        // … der neue löst denselben Punkt auf.
        $resolved = $service->scan($run, $reissued['token']);
        $this->assertSame($checkpoint->id, $resolved->id);
    }

    /** Je Route läuft höchstens ein Rundgang. */
    public function test_only_one_running_patrol_per_route(): void {
        ['route' => $route] = $this->route(1);
        $service = app(PatrolService::class);
        $service->start($route, $this->admin);

        $this->expectException(\RuntimeException::class);
        $service->start($route, $this->admin);
    }

    public function test_page_requires_dispatch_rights(): void {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('patrols.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('patrols.index'))->assertOk();
    }
}
