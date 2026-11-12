<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OfflineConflictResolutionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Enums\Sync\SyncConflictResolution;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Models\Audit\AuditLog;
use App\Models\Integration\SyncCommand;
use App\Models\Platform\User;
use App\Models\Time\{Attendance, TimeCorrectionRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zeitkorrektur offline und Konfliktliste (MVP-1595): Der Dialog auf „Heute“
 * löst `attendance.correct` aus (online als normaler POST mit derselben
 * Versionsprüfung), jede Entscheidung über einen Konflikt schreibt
 * `sync.conflict.resolved` genau einmal.
 */
class OfflineConflictResolutionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string REASON = 'Vergessen auszustempeln, Baustelle ohne Empfang am Nachmittag.';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 18:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function attendance(?User $owner = null): Attendance {
        return Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => ($owner ?? $this->user)->id,
            'started_at' => '2030-01-16 08:00:00',
            'ended_at' => '2030-01-16 16:00:00',
            'date' => '2030-01-16',
        ]);
    }

    /** Konflikt über den Sync-Weg erzeugen: Stempelung nach dem Öffnen des Dialogs geändert. */
    private function conflict(): string {
        $attendance = $this->attendance();
        $stale = $attendance->correctionVersion();
        $attendance->forceFill(['started_at' => '2030-01-16 09:00:00'])->save();
        $uuid = (string) Str::uuid();

        $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => $uuid,
                'type' => 'attendance.correct',
                'payload' => ['attendance' => $attendance->sqid, 'base_version' => $stale, 'started_at' => '2030-01-16T07:30', 'reason' => self::REASON],
            ]],
        ])->assertJsonPath('results.0.status', 'conflict');

        return $uuid;
    }

    public function test_online_dialog_submits_a_correction_request(): void {
        $attendance = $this->attendance();

        $this->actingAs($this->user)
            ->post(route('attendance.correct', $attendance), [
                'base_version' => $attendance->correctionVersion(),
                'started_at' => '2030-01-16T07:30',
                'ended_at' => '2030-01-16T16:00',
                'break_minutes' => '30',
                'reason' => self::REASON,
            ])
            ->assertSessionHas('success', __('time-correction.attendance.submitted'));

        $request = TimeCorrectionRequest::query()->sole();
        $this->assertSame(TimeCorrectionStatus::Submitted, $request->status);
        $item = $request->items->first();
        $this->assertSame('2030-01-16 07:30:00', $item->after['started_at']);
        $this->assertSame(30, (int) $item->after['break_minutes_manual']);
    }

    public function test_online_dialog_with_a_changed_clocking_overwrites_nothing(): void {
        $attendance = $this->attendance();
        $stale = $attendance->correctionVersion();
        $attendance->forceFill(['ended_at' => '2030-01-16 17:00:00'])->save();

        $this->actingAs($this->user)
            ->post(route('attendance.correct', $attendance), ['base_version' => $stale, 'started_at' => '2030-01-16T07:30', 'reason' => self::REASON])
            ->assertSessionHas('error', __('time-correction.attendance.changed'));

        $this->assertSame(0, TimeCorrectionRequest::query()->count());
    }

    public function test_foreign_clocking_cannot_be_corrected_online(): void {
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $attendance = $this->attendance($colleague);

        $this->actingAs($this->user)
            ->post(route('attendance.correct', $attendance), ['base_version' => $attendance->correctionVersion(), 'started_at' => '2030-01-16T07:30', 'reason' => self::REASON])
            ->assertNotFound();
    }

    public function test_offline_payload_from_the_dialog_is_read_as_wall_clock_time(): void {
        $attendance = $this->attendance();

        $this->actingAs($this->user)->postJson(route('api.internal.sync.commands'), [
            'commands' => [[
                'client_uuid' => (string) Str::uuid(),
                'type' => 'attendance.correct',
                'payload' => ['attendance' => $attendance->sqid, 'base_version' => $attendance->correctionVersion(), 'started_at' => '2030-01-16T07:15', 'ended_at' => '2030-01-16T16:00', 'reason' => self::REASON],
            ]],
        ])->assertJsonPath('results.0.status', 'applied');

        $this->assertSame('2030-01-16 07:15:00', TimeCorrectionRequest::query()->sole()->items->first()->after['started_at']);
    }

    public function test_taking_the_server_state_records_the_decision_once(): void {
        $uuid = $this->conflict();

        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $uuid, 'resolution' => 'server'])
            ->assertOk()
            ->assertJsonPath('resolved', true);
        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $uuid, 'resolution' => 'discarded'])
            ->assertOk();

        $row = SyncCommand::query()->where('client_uuid', $uuid)->sole();
        $this->assertSame(SyncConflictResolution::Server, $row->resolution);
        $this->assertNotNull($row->resolved_at);

        $audit = AuditLog::query()->where('event', 'sync.conflict.resolved')->sole();
        $this->assertSame('server', $audit->changes['resolution']);
        $this->assertSame('attendance.correct', $audit->changes['type']);
        $this->assertSame($uuid, $audit->changes['client_uuid']);
    }

    public function test_resending_names_the_new_command(): void {
        $uuid = $this->conflict();

        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $uuid, 'resolution' => 'resent'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('resent_client_uuid');

        $newUuid = (string) Str::uuid();
        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $uuid, 'resolution' => 'resent', 'resent_client_uuid' => $newUuid, 'edited' => true])
            ->assertOk();

        $this->assertSame($newUuid, SyncCommand::query()->where('client_uuid', $uuid)->value('resent_client_uuid'));
        $audit = AuditLog::query()->where('event', 'sync.conflict.resolved')->sole();
        $this->assertTrue($audit->changes['edited']);
        $this->assertSame($newUuid, $audit->changes['resent_client_uuid']);
    }

    public function test_conflict_of_another_person_is_not_found(): void {
        $uuid = $this->conflict();
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($colleague)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $uuid, 'resolution' => 'discarded'])
            ->assertNotFound();

        $this->assertNull(SyncCommand::query()->where('client_uuid', $uuid)->value('resolved_at'));
        $this->assertSame(0, AuditLog::query()->where('event', 'sync.conflict.resolved')->count());
    }

    public function test_changes_page_lists_open_conflicts_of_all_devices(): void {
        $open = $this->conflict();
        $resolved = $this->conflict();
        $this->actingAs($this->user)
            ->postJson(route('api.internal.sync.conflicts.resolve'), ['client_uuid' => $resolved, 'resolution' => 'discarded'])
            ->assertOk();

        $html = $this->actingAs($this->user)->get(route('offline.changes'))->assertOk()->getContent();

        $this->assertStringContainsString($open, (string) $html);
        $this->assertStringNotContainsString($resolved, (string) $html);
        $this->assertStringContainsString(e(route('api.internal.sync.conflicts.resolve')), (string) $html);
    }
}
