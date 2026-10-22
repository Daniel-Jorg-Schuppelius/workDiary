<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Calendar;

use App\Models\Diary\OnCallShift;
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CalendarTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        // D12 (Vollscan 2026-08-23): Mittmonats-Werktag statt Echtzeit —
        // an Wochen-/Monatsgrenzen kippten die relativen Zeitrechnungen.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-06-17 10:00:00'));
        $this->setUpOrganization();
    }

    public function test_index_renders_for_authenticated_user(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($user)->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('id="calendar"', false);
    }

    public function test_events_endpoint_returns_users_own_shifts(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $start = now()->startOfWeek()->addDay()->setTime(8, 0);
        $end = (clone $start)->addHours(4);

        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);
        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $other->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $rangeStart = $start->copy()->subDays(7)->toIso8601String();
        $rangeEnd = $start->copy()->addDays(7)->toIso8601String();

        $res = $this->actingAs($user)
            ->getJson(route('calendar.events', ['start' => $rangeStart, 'end' => $rangeEnd]));

        $res->assertOk()->assertJsonCount(1);
        $this->assertStringStartsWith('shift-', $res->json('0.id'));
    }

    public function test_events_endpoint_requires_auth(): void {
        $this->getJson(route('calendar.events'))->assertStatus(401);
    }

    public function test_events_endpoint_team_scope_filters_by_sqid_user(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $workerA = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $workerB = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $start = now()->startOfWeek()->addDay()->setTime(8, 0);
        $end = (clone $start)->addHours(4);

        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $workerA->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);
        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $workerB->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $res = $this->actingAs($admin)->getJson(route('calendar.events', [
            'start' => $start->copy()->subDays(7)->toIso8601String(),
            'end' => $start->copy()->addDays(7)->toIso8601String(),
            'team' => 1,
            'user' => Sqid::encode(User::class, $workerB->id),
        ]));

        $res->assertOk()->assertJsonCount(1);
        $this->assertStringContainsString($workerB->name, (string) $res->json('0.title'));
    }

    public function test_events_endpoint_team_scope_accepts_numeric_user_fallback(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $workerA = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $workerB = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $start = now()->startOfWeek()->addDay()->setTime(8, 0);
        $end = (clone $start)->addHours(4);

        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $workerA->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);
        OnCallShift::create([
            'organization_id' => $this->organization->id,
            'user_id' => $workerB->id,
            'start_at' => $start,
            'end_at' => $end,
        ]);

        $res = $this->actingAs($admin)->getJson(route('calendar.events', [
            'start' => $start->copy()->subDays(7)->toIso8601String(),
            'end' => $start->copy()->addDays(7)->toIso8601String(),
            'team' => 1,
            'user' => (string) $workerA->id,
        ]));

        $res->assertOk()->assertJsonCount(1);
        $this->assertStringContainsString($workerA->name, (string) $res->json('0.title'));
    }

    /** Fristen offener Ausschreibungen kommen über den Erweiterungspunkt des Kalenders (MVP-1299). */
    public function test_events_include_tender_deadlines_of_the_responsible_person(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $other = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $base = [
            'organization_id' => $this->organization->id, 'kind' => 'tender', 'status' => \App\Enums\Applications\ApplicationOpportunityStatus::InProgress, 'created_by' => $admin->id,
        ];
        $mine = \App\Models\Applications\ApplicationOpportunity::query()->create([...$base, 'title' => 'Schule Nord', 'responsible_user_id' => $admin->id,
            'submission_deadline' => '2026-06-19', 'opening_at' => '2026-06-19 10:00:00', 'binding_until' => '2026-09-30']);
        \App\Models\Applications\ApplicationOpportunity::query()->create([...$base, 'title' => 'Halle Süd', 'responsible_user_id' => $other->id, 'submission_deadline' => '2026-06-18']);

        $res = $this->actingAs($admin)->getJson(route('calendar.events', ['start' => '2026-06-15T00:00:00+02:00', 'end' => '2026-06-21T23:59:59+02:00']))->assertOk();
        $tenderEvents = collect($res->json())->filter(fn (array $event): bool => str_starts_with($event['id'], 'tender-'))->values();

        $this->assertCount(2, $tenderEvents);
        $this->assertSame(['tender-' . $mine->sqid . '-submission_deadline', 'tender-' . $mine->sqid . '-opening_at'], $tenderEvents->pluck('id')->all());
        $this->assertSame('2026-06-19', $tenderEvents[0]['start']);
        $this->assertStringContainsString('Schule Nord', $tenderEvents[0]['title']);
        $this->assertSame(route('tenders.show', $mine), $tenderEvents[0]['url']);

        $team = $this->actingAs($admin)->getJson(route('calendar.events', ['start' => '2026-06-15T00:00:00+02:00', 'end' => '2026-06-21T23:59:59+02:00', 'team' => 1]))->assertOk();
        $this->assertCount(3, collect($team->json())->filter(fn (array $event): bool => str_starts_with($event['id'], 'tender-')));

        // Dieselben Fristen im persönlichen ICS-Feed, ganztägig bis auf die Eröffnung.
        $ics = (string) $this->actingAs($admin)->get(route('events.ics.personal'))->assertOk()->getContent();
        $this->assertStringContainsString('UID:tender-' . $mine->sqid . '-submission_deadline@workdiary', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260619', $ics);
        $this->assertStringContainsString('UID:tender-' . $mine->sqid . '-binding_until@workdiary', $ics);
        $this->assertStringNotContainsString('Halle', $ics);
    }
}
