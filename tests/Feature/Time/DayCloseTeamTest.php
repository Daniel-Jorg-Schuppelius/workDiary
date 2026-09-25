<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DayCloseTeamTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Enums\TimeApproval\{DayClosureStatus, MonthClosureStatus};
use App\Enums\User\UserRole;
use App\Models\Diary\DayClosure;
use App\Models\Platform\User;
use App\Models\Time\{Attendance, MonthClosure};
use App\Services\TimeApproval\DayCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-884: Team heute — Anwesenheit jetzt und offene Tagesabschlüsse. */
class DayCloseTeamTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->travelTo(CarbonImmutable::create(2026, 6, 10, 14, 0, 0));
    }

    private function attendance(User $user, string $date, ?string $end = '12:00'): void {
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'date' => $date,
            'started_at' => CarbonImmutable::parse($date . ' 08:00'),
            'ended_at' => $end !== null ? CarbonImmutable::parse($date . ' ' . $end) : null,
        ]);
    }

    public function test_open_days_skip_closed_days_locked_months_today_and_other_organizations(): void {
        $anna = $this->orgUser(['name' => 'Anna']);
        $ben = $this->orgUser(['name' => 'Ben']);
        $this->attendance($anna, '2026-06-08');
        $this->attendance($anna, '2026-06-09');
        $this->attendance($ben, '2026-06-09');
        $this->attendance($ben, '2026-06-10', null);
        $this->attendance($ben, '2026-05-29');
        DayClosure::query()->create(['organization_id' => $this->organization->id, 'user_id' => $anna->id, 'day' => '2026-06-08', 'status' => DayClosureStatus::Closed->value]);
        MonthClosure::query()->create(['organization_id' => $this->organization->id, 'user_id' => $ben->id, 'period_year' => 2026, 'period_month' => 5, 'status' => MonthClosureStatus::Locked->value]);
        Attendance::factory()->create(['date' => '2026-06-09']);

        $open = app(DayCloseService::class)->openDays($this->organization->id, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-06-10'));

        $this->assertSame(
            [['Anna', '2026-06-09'], ['Ben', '2026-06-09']],
            array_map(static fn (array $row): array => [$row['user']->name, $row['day']->toDateString()], $open),
        );
    }

    public function test_team_page_shows_presence_and_open_days_for_team_leads_only(): void {
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $worker = $this->orgUser(['name' => 'Carla Monteur']);
        $this->attendance($worker, '2026-06-09');
        $this->attendance($worker, '2026-06-10', null);

        $this->actingAs($lead)->get(route('day-close.team'))
            ->assertOk()
            ->assertSee('Carla Monteur')
            ->assertSee(__('day-close.team.open_days'))
            ->assertSee(route('day-close.show', ['date' => '2026-06-09', 'user' => $worker->sqid]));

        $this->actingAs($lead)->get(route('day-close.show'))->assertSee(route('day-close.team'));
        $this->actingAs($worker)->get(route('day-close.team'))->assertForbidden();
    }
}
