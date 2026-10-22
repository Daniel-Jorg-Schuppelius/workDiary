<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolOfflineScanAndReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Patrols;

use App\Models\Location\LocationDeviceToken;
use App\Models\Patrol\{PatrolRoute, PatrolScan};
use App\Models\Platform\User;
use App\Services\Patrol\PatrolService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Offline-Scan mit Gerätezeit (MVP-1291) und Rundgangsbericht über einen Zeitraum (MVP-1292). */
final class PatrolOfflineScanAndReportTest extends TestCase {
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

    /** @return array{route: PatrolRoute, run: \App\Models\Patrol\PatrolRun, tokens: list<string>} */
    private function runningPatrol(): array {
        $route = PatrolRoute::query()->create(['organization_id' => $this->organization->id, 'name' => 'Nachtrunde', 'active' => true, 'created_by' => $this->admin->id]);
        $tokens = [
            $this->service->addCheckpoint($route, 'Tor', 0, 5)['token'],
            $this->service->addCheckpoint($route, 'Halle', 20, 5)['token'],
        ];

        return ['route' => $route, 'run' => $this->service->start($route, $this->admin), 'tokens' => $tokens];
    }

    public function test_offline_scan_counts_with_the_device_time(): void {
        Carbon::setTestNow('2026-10-22 22:00:00');
        ['run' => $run, 'tokens' => $tokens] = $this->runningPatrol();
        Carbon::setTestNow('2026-10-22 22:45:00');

        // Gescannt um 22:21 ohne Netz, nachgeliefert um 22:45: zählt pünktlich.
        $this->actingAs($this->admin)->postJson(route('api.internal.sync.commands'), ['commands' => [[
            'client_uuid' => (string) Str::uuid(),
            'type' => 'patrol.scan',
            'payload' => ['run' => $run->sqid, 'token' => $tokens[1], 'at' => '2026-10-22T22:21:00+00:00'],
            'captured_at' => '2026-10-22T22:21:00+00:00',
        ]]])->assertOk()->assertJsonPath('results.0.status', 'applied');

        $scan = PatrolScan::query()->sole();
        $this->assertSame('2026-10-22 22:21:00', $scan->scanned_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, (int) $scan->delta_minutes);
        $this->assertTrue((bool) $scan->in_window);
    }

    public function test_implausible_device_time_is_rejected_and_api_accepts_scanned_at(): void {
        Carbon::setTestNow('2026-10-22 22:00:00');
        ['run' => $run, 'tokens' => $tokens] = $this->runningPatrol();
        Carbon::setTestNow('2026-10-22 22:10:00');

        try {
            $this->service->scan($run, $tokens[0], CarbonImmutable::parse('2026-10-22 21:30:00'));
            $this->fail('Scan vor dem Rundgangsbeginn muss scheitern');
        } catch (\RuntimeException $e) {
            $this->assertSame(__('patrol.scan.implausibleTime'), $e->getMessage());
        }

        $device = LocationDeviceToken::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'label' => 'Wächtergerät',
            'token_hash' => LocationDeviceToken::hashToken('GERAET-TOKEN-1'),
        ]);
        $this->postJson(route('api.patrol.scan', ['token' => 'GERAET-TOKEN-1']), ['checkpoint' => $tokens[0], 'scanned_at' => '2026-10-22T22:02:00+00:00'])
            ->assertOk();
        $this->assertSame('2026-10-22 22:02:00', PatrolScan::query()->sole()->scanned_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($device->fresh()->last_used_at);
    }

    public function test_period_report_lists_runs_with_punctuality_and_exports(): void {
        Carbon::setTestNow('2026-10-20 22:00:00');
        ['run' => $run, 'tokens' => $tokens] = $this->runningPatrol();
        $this->service->scan($run, $tokens[0]);
        Carbon::setTestNow('2026-10-20 22:40:00');
        $this->service->scan($run, $tokens[1]);
        $this->service->complete($run->refresh(), $this->admin, 'Halle verspätet');
        Carbon::setTestNow('2026-10-22 10:00:00');

        $this->actingAs($this->admin)->get(route('patrols.report'))
            ->assertOk()
            ->assertSee('Nachtrunde')
            ->assertSee('Halle verspätet')
            ->assertSee('50 %');

        $csv = (string) $this->actingAs($this->admin)->get(route('patrols.report', ['export' => 'csv']))->assertOk()->getContent();
        $this->assertStringContainsString('Nachtrunde', $csv);
        $this->actingAs($this->admin)->get(route('patrols.index'))->assertSee(route('patrols.report'), false);
    }
}
