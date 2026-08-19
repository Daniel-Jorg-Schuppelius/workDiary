<?php
/*
 * Created on   : Tue Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PatrolFollowUpTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Patrols;

use App\Models\{DiaryEntry, EntryType, User};
use App\Models\Location\LocationDeviceToken;
use App\Models\Patrol\{PatrolRoute, PatrolRun};
use App\Services\Patrol\PatrolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Folgepunkte der Wächterrundgänge (Feature 089): Geräte-Scan-Endpunkt
 * (NFC-Leser meldet Checkpoint-Token über das Standort-Geräte-Token),
 * Wachbuch-Anbindung (Abschluss → Tagebucheintrag) und der Berichts-PDF.
 */
final class PatrolFollowUpTest extends TestCase {
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
    private function route(int $checkpoints = 1): array {
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

    public function test_device_scan_rejects_an_invalid_token(): void {
        $this->postJson('/api/patrol/scan/GIBT-ES-NICHT', ['checkpoint' => 'x'])
            ->assertStatus(401);
    }

    public function test_device_scan_requires_a_running_patrol(): void {
        ['tokens' => $tokens] = $this->route();
        [, $plain] = LocationDeviceToken::issue($this->admin, 'NFC-Leser Tor 1');

        $this->postJson("/api/patrol/scan/{$plain}", ['checkpoint' => $tokens[0]])
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_running_patrol');
    }

    public function test_device_scan_books_on_the_running_patrol(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route();
        $run = app(PatrolService::class)->start($route, $this->admin);
        [$device, $plain] = LocationDeviceToken::issue($this->admin, 'NFC-Leser Tor 1');

        $this->postJson("/api/patrol/scan/{$plain}", ['checkpoint' => $tokens[0]])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('run', $run->sqid);

        $this->assertSame(1, $run->scans()->count());
        $this->assertNotNull($device->fresh()?->last_used_at);
    }

    public function test_device_scan_rejects_an_unknown_checkpoint(): void {
        [, $plain] = LocationDeviceToken::issue($this->admin, 'NFC-Leser Tor 1');

        $this->postJson("/api/patrol/scan/{$plain}", ['checkpoint' => 'UNBEKANNT'])
            ->assertStatus(404)
            ->assertJsonPath('error', 'unknown_checkpoint');
    }

    /** Abschluss schreibt ins Wachbuch, wenn die Org den Eintragstyp führt. */
    public function test_completion_writes_a_logbook_entry(): void {
        EntryType::factory()->create([
            'organization_id' => $this->organization->id,
            'slug' => 'revierfahrt',
            'label' => 'Revierfahrt',
        ]);

        ['route' => $route, 'tokens' => $tokens] = $this->route();
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);
        $service->scan($run, $tokens[0]);
        $service->complete($run, $this->admin);

        $entry = DiaryEntry::query()->firstOrFail();
        $this->assertSame('Rundgang: Revierfahrt Nacht', $entry->title);
        $this->assertSame($this->admin->id, $entry->user_id);
    }

    /** Ohne den Eintragstyp bleibt das Wachbuch bewusst leer — kein Zwang. */
    public function test_completion_without_entry_type_writes_no_logbook_entry(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route();
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);
        $service->scan($run, $tokens[0]);
        $service->complete($run, $this->admin);

        $this->assertSame(PatrolRun::STATUS_COMPLETED, $run->fresh()?->status);
        $this->assertSame(0, DiaryEntry::query()->count());
    }

    public function test_finished_run_exports_a_pdf_report(): void {
        ['route' => $route, 'tokens' => $tokens] = $this->route();
        $service = app(PatrolService::class);
        $run = $service->start($route, $this->admin);
        $service->scan($run, $tokens[0]);
        $service->complete($run, $this->admin);

        $this->actingAs($this->admin)
            ->get(route('patrols.runs.show', ['patrolRun' => $run, 'export' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
