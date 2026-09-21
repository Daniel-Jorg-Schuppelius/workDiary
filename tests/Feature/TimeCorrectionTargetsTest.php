<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionTargetsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\Permission as P;
use App\Models\{Attendance, Organization, TimeCorrectionRequest, TimeEntry, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Zeitkorrektur-Antrag (UI-Fuzz 2026-09-21, MVP-829): Das Ziel einer Position
 * wird aus den Buchungen und Anwesenheiten des Bezugstags gewählt statt über
 * eine interne numerische ID.
 */
class TimeCorrectionTargetsTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->employee = User::factory()->create(['organization_id' => $this->org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        SpatiePermission::findOrCreate(P::CorrectionCreateOwn->value, 'web');
        $this->employee->givePermissionTo(P::CorrectionCreateOwn->value);
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    public function test_targets_list_the_own_entries_of_the_day_by_sqid(): void {
        $attendance = Attendance::factory()->create([
            'organization_id' => $this->org->id, 'user_id' => $this->employee->id,
            'date' => '2026-06-01', 'started_at' => '2026-06-01 06:00:00', 'ended_at' => '2026-06-01 14:00:00',
        ]);
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->org->id, 'user_id' => $this->employee->id,
            'date' => '2026-06-01', 'started_at' => '2026-06-01 07:00:00', 'ended_at' => '2026-06-01 09:00:00', 'minutes' => 120,
        ]);
        TimeEntry::factory()->create(['organization_id' => $this->org->id, 'date' => '2026-06-01']);

        $response = $this->actingAs($this->employee)
            ->getJson(route('corrections.targets', ['date' => '2026-06-01']))
            ->assertOk();

        $this->assertSame([$entry->sqid], array_column($response->json(TimeEntry::class), 'id'));
        $this->assertSame([$attendance->sqid], array_column($response->json(Attendance::class), 'id'));
        $this->assertStringContainsString('–', (string) $response->json(Attendance::class . '.0.label'));
    }

    public function test_targets_of_colleagues_require_the_on_behalf_permission(): void {
        $colleague = User::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->employee)
            ->getJson(route('corrections.targets', ['date' => '2026-06-01', 'user' => $colleague->sqid]))
            ->assertForbidden();
    }

    public function test_store_accepts_the_selected_target_as_sqid(): void {
        $attendance = Attendance::factory()->create([
            'organization_id' => $this->org->id, 'user_id' => $this->employee->id,
            'date' => '2026-06-01', 'started_at' => '2026-06-01 06:00:00', 'ended_at' => '2026-06-01 14:00:00',
        ]);

        $this->actingAs($this->employee)->post(route('corrections.store'), [
            'scope_date' => '2026-06-01',
            'reason' => 'Gehen vergessen zu stempeln, tatsächlich bis 15 Uhr gearbeitet.',
            'items' => [[
                'target_type' => Attendance::class,
                'target_id' => $attendance->sqid,
                'action' => 'update',
                'after' => json_encode(['ended_at' => '2026-06-01 15:00:00']),
            ]],
        ])->assertSessionHasNoErrors();

        $request = TimeCorrectionRequest::query()->where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame($attendance->id, (int) $request->items()->firstOrFail()->target_id);
    }
}
