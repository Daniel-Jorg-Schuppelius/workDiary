<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceStatusTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus, PresenceStatusKind};
use App\Models\Audit\AuditLog;
use App\Models\Chat\Channel;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Models\Time\{Attendance, PresenceStatus};
use App\Services\Attendance\{AttendanceClockService, PresenceStatusService};
use App\Services\Attendance\Demo\PresenceStatusDemoBlock;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Support\MorphMap;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669): „nicht
 * stören“, „kurz weg“ (mit Ende), „außer Haus“ (mit Ende) — nur der aktuelle
 * Stand, kein Verlauf; erlischt mit dem Ende und beim Ausstempeln; sichtbar
 * auf dem Board, im Chat und in der Personenauswahl.
 */
final class PresenceStatusTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
        $this->employee = $this->orgUser(['name' => 'Paula Praesent']);
    }

    private function enableBoard(): void {
        $this->organization->update(['settings' => ['presence' => ['board_enabled' => '1']]]);
        app()->instance('currentOrganization', $this->organization->fresh());
    }

    private function clockedIn(User $user): void {
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'started_at' => now()->subHours(2),
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
            'source' => AttendanceSource::Clock->value,
        ]);
    }

    private function rows(): int {
        return PresenceStatus::query()->withoutGlobalScope(OrganizationScope::class)->count();
    }

    public function test_without_the_board_there_is_no_self_status(): void {
        $this->actingAs($this->employee)->get(route('presence.status.edit'))->assertNotFound();
        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'do_not_disturb'])->assertNotFound();
        $this->assertSame(0, $this->rows());
    }

    public function test_a_person_sets_their_own_status_and_colleagues_see_it_on_the_board(): void {
        $this->enableBoard();
        $this->clockedIn($this->employee);
        $colleague = $this->orgUser(['name' => 'Karl Kollege']);

        $this->actingAs($this->employee)->get(route('presence.status.edit', ['dialog' => 1]))->assertOk()->assertSee('name="kind"', false);
        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'do_not_disturb', 'duration' => ''])
            ->assertRedirect(route('presence.board'))
            ->assertSessionHasNoErrors();

        $this->actingAs($colleague)->get(route('presence.board'))
            ->assertOk()
            ->assertSee('Paula Praesent')
            ->assertSee('data-presence-status="do_not_disturb"', false)
            ->assertSee(__('presence.kind.do_not_disturb'));
    }

    public function test_brief_away_and_out_of_office_need_an_end_and_a_new_status_replaces_the_old_one(): void {
        $this->enableBoard();
        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'brief_away', 'duration' => ''])
            ->assertSessionHasErrors('valid_until');
        $this->assertSame(0, $this->rows());

        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'brief_away', 'duration' => 30])->assertSessionHasNoErrors();
        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'out_of_office', 'valid_until' => '2026-11-12T15:30'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->rows(), 'Nur der aktuelle Stand, kein Verlauf.');
        $status = app(PresenceStatusService::class)->current($this->employee);
        $this->assertSame(PresenceStatusKind::OutOfOffice, $status?->kind);
        $this->assertSame(
            CarbonImmutable::parse('2026-11-12 15:30', \App\Support\Tz::ofOrganization($this->organization))->utc()->toDateTimeString(),
            $status->valid_until?->toDateTimeString(),
        );
        $this->assertSame(0, AuditLog::query()->where('auditable_type', MorphMap::stableKey(PresenceStatus::class))->count(), 'Kein Audit, kein Verlauf.');

        $this->actingAs($this->employee)->post(route('presence.status.update'), ['kind' => 'out_of_office', 'valid_until' => '2026-11-12T09:00'])
            ->assertSessionHasErrors('valid_until');
    }

    public function test_a_status_ends_with_its_end_time_and_leaves_no_row(): void {
        $this->enableBoard();
        $service = app(PresenceStatusService::class);
        $service->set($this->employee, PresenceStatusKind::BriefAway, CarbonImmutable::now()->addMinutes(15));
        $this->assertNotNull($service->current($this->employee));

        Carbon::setTestNow('2026-11-12 10:20:00');
        $service->flush();

        $this->assertNull($service->current($this->employee));
        $this->assertSame(0, $this->rows(), 'Abgelaufene Stände werden beim Lesen gelöscht.');
    }

    public function test_clocking_out_and_resetting_clear_the_status(): void {
        $this->enableBoard();
        $clock = app(AttendanceClockService::class);
        $clock->clockIn($this->employee);
        app(PresenceStatusService::class)->set($this->employee, PresenceStatusKind::DoNotDisturb, null);

        $clock->clockOut($this->employee);
        $this->assertSame(0, $this->rows(), 'Der Stand erlischt beim Ausstempeln.');

        app(PresenceStatusService::class)->set($this->employee, PresenceStatusKind::DoNotDisturb, null);
        $this->actingAs($this->employee)->delete(route('presence.status.destroy'))->assertRedirect(route('presence.board'));
        $this->assertSame(0, $this->rows());
    }

    public function test_chat_and_person_selection_show_the_status_only_with_the_board(): void {
        $colleague = $this->orgUser(['name' => 'Karl Kollege']);
        app(PresenceStatusService::class)->set($this->employee, PresenceStatusKind::DoNotDisturb, null);
        $channel = Channel::create(['organization_id' => $this->organization->id, 'name' => 'dm', 'slug' => 'dm-pk', 'type' => 'direct', 'visibility' => 'private', 'created_by' => $colleague->id]);
        $channel->members()->attach($colleague->id, ['role' => 'owner']);
        $channel->members()->attach($this->employee->id, ['role' => 'member']);

        $this->actingAs($colleague)->get(route('chat.show', $channel))
            ->assertOk()
            ->assertDontSee('data-presence-status=', false);

        $this->enableBoard();
        app(PresenceStatusService::class)->flush();
        $colleague->unsetRelations();
        $this->actingAs($colleague)->get(route('chat.show', $channel))
            ->assertOk()
            ->assertSee('data-presence-status="do_not_disturb"', false);

        $html = (string) $this->blade('<x-user-select name="user_id" :users="$users" />', ['users' => collect([$this->employee, $colleague])]);
        $this->assertStringContainsString('Paula Praesent · ' . __('presence.kind.do_not_disturb'), preg_replace('/\s+/', ' ', $html) ?? '');
    }

    public function test_the_demo_block_sets_an_invented_status(): void {
        $second = $this->orgUser(['name' => 'Demo Zwei']);
        $context = new DemoSeedContext($this->organization, $this->employee, collect([$this->employee, $second]), [], null, null, null);

        $this->assertSame(['presence_statuses' => 1], app(PresenceStatusDemoBlock::class)->seed($context));
        $this->assertSame(PresenceStatusKind::DoNotDisturb, app(PresenceStatusService::class)->current($second)?->kind);

        app(PresenceStatusDemoBlock::class)->purge($this->organization);
        $this->assertSame(0, $this->rows());
    }
}
