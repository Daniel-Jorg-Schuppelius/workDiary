<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexRecomputeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Flextime;

use App\Dashboard\Widgets\FlexBalanceWidget;
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeApproval\MonthClosureStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Enums\WorkSchedule\ScheduleType;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\{FlexBalance, MonthClosure, TimeEntry, WorkSchedule};
use App\Services\Flextime\{FlexCalculator, FlexForecastService};
use App\Services\TimeApproval\MonthClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** Gespeicherter Gleitzeitsaldo (MVP-1550): Lauf, Übertrag, Leser, Monatsfreigabe. */
class FlexRecomputeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-06-12 12:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Flex',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->user->id,
        ]);
        $this->schedule($this->user);
    }

    public function test_run_writes_previous_and_current_month_with_carry_over(): void {
        [$mayTarget, $juneTarget] = $this->targets();
        $this->assertGreaterThan(0, $juneTarget);
        $this->book('2030-05-02', $mayTarget + 120);
        $this->book('2030-06-03', $juneTarget - 30);
        // Heute zählt noch nicht: weder Soll noch Ist.
        $this->book('2030-06-12', 600);

        $this->artisan('flex:recompute')->assertExitCode(0);

        $may = $this->balance(2030, 5);
        $this->assertSame($mayTarget, $may->target_minutes);
        $this->assertSame(0, $may->carry_over_minutes);
        $this->assertSame(120, $may->balance_minutes);
        $this->assertSame($this->organization->id, (int) $may->organization_id);

        $june = $this->balance(2030, 6);
        $this->assertSame($juneTarget, $june->target_minutes);
        $this->assertSame($juneTarget - 30, $june->actual_minutes);
        $this->assertSame(120, $june->carry_over_minutes);
        $this->assertSame(90, $june->balance_minutes);
        $this->assertNotNull($june->computed_at);
    }

    public function test_run_is_idempotent_and_skips_trust_and_deactivated_users(): void {
        $trust = $this->orgUser();
        $this->schedule($trust, ScheduleType::Trust);
        $gone = $this->orgUser();
        $this->schedule($gone);
        $gone->forceFill(['deactivated_at' => now()])->save();

        $this->artisan('flex:recompute')->assertExitCode(0);
        $this->artisan('flex:recompute')->assertExitCode(0);

        $this->assertSame(2, FlexBalance::query()->where('user_id', $this->user->id)->count());
        $this->assertSame(0, FlexBalance::query()->whereIn('user_id', [$trust->id, $gone->id])->count());
    }

    public function test_from_option_backfills_without_gaps(): void {
        $this->artisan('flex:recompute', ['--from' => '2030-03'])->assertExitCode(0);

        $this->assertSame([3, 4, 5, 6], FlexBalance::query()->where('user_id', $this->user->id)->orderBy('month')->pluck('month')->all());
        $this->assertSame($this->balance(2030, 5)->balance_minutes, $this->balance(2030, 6)->carry_over_minutes);

        $this->artisan('flex:recompute', ['--from' => '2030-13'])->assertExitCode(1);
    }

    public function test_readers_see_the_written_balance(): void {
        [$mayTarget, $juneTarget] = $this->targets();
        $this->book('2030-05-02', $mayTarget + 120);
        $this->book('2030-06-03', $juneTarget);
        $this->artisan('flex:recompute')->assertExitCode(0);

        $this->actingAs($this->user);
        $html = (string) app(FlexBalanceWidget::class)->render($this->user);
        $this->assertStringNotContainsString('Noch kein Saldo', $html);
        $this->assertStringContainsString('+2:00', $html);

        $this->assertSame(120, app(FlexForecastService::class)->forecast($this->user)['start_balance']);
    }

    public function test_month_approval_and_reopen_recompute_the_month_and_its_followers(): void {
        $this->artisan('flex:recompute')->assertExitCode(0);
        $before = $this->balance(2030, 6)->balance_minutes;

        $closure = MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'period_year' => 2030,
            'period_month' => 5,
            'status' => MonthClosureStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $this->book('2030-05-03', 60);
        $admin = $this->orgUser();
        $closure = app(MonthClosureService::class)->approve($closure, $admin);

        $this->assertSame($before + 60, $this->balance(2030, 6)->balance_minutes);

        $this->book('2030-05-06', 30);
        app(MonthClosureService::class)->reopen($closure, $admin, 'Nachtrag einer vergessenen Buchung im Mai.');

        $this->assertSame($before + 90, $this->balance(2030, 6)->balance_minutes);
    }

    public function test_page_api_and_tile_show_the_same_account(): void {
        [$mayTarget, $juneTarget] = $this->targets();
        $this->book('2030-05-02', $mayTarget + 120);
        $this->book('2030-06-03', $juneTarget - 30);
        $this->book('2030-06-12', 600);
        $this->artisan('flex:recompute')->assertExitCode(0);
        $stored = $this->balance(2030, 6);

        $account = app(FlexCalculator::class)->accountMonth($this->user, 2030, 6);
        $this->assertSame($stored->target_minutes, $account['target']);
        $this->assertSame($stored->actual_minutes, $account['actual']);
        $this->assertSame($stored->balance_minutes, $account['account']);
        $this->assertSame(120, $account['carry']);
        $this->assertSame('2030-06-11', $account['settled_through']);
        // Die Tagesliste zeigt den ganzen Monat, auch heute.
        $this->assertSame(600, $account['days']['2030-06-12']['actual']);
        $this->assertArrayHasKey('2030-06-30', $account['days']);

        $this->actingAs($this->user)
            ->get(route('flex.index', ['activeMonth' => '2030-06']))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['account'] === $stored->balance_minutes && $summary['target'] === $stored->target_minutes);

        $this->assertNull(app(FlexCalculator::class)->accountMonth($this->user, 2030, 5)['settled_through']);
    }

    public function test_approved_month_stays_frozen_until_reopened(): void {
        $closure = MonthClosure::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'period_year' => 2030,
            'period_month' => 5,
            'status' => MonthClosureStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $this->book('2030-05-03', 60);
        $admin = $this->orgUser();
        $closure = app(MonthClosureService::class)->approve($closure, $admin);
        $may = $this->balance(2030, 5);
        $this->assertTrue($may->locked);
        $this->assertFalse($this->balance(2030, 6)->locked);

        // Ein Nachtrag am freigegebenen Monat ändert den eingefrorenen Stand nicht, auch nicht im Nachtlauf.
        $this->book('2030-05-06', 30);
        $this->artisan('flex:recompute', ['--from' => '2030-05'])->assertExitCode(0);
        $this->assertSame($may->balance_minutes, $this->balance(2030, 5)->balance_minutes);

        app(MonthClosureService::class)->reopen($closure, $admin, 'Nachtrag einer vergessenen Buchung im Mai.');
        $reopened = $this->balance(2030, 5);
        $this->assertFalse($reopened->locked);
        $this->assertSame($may->balance_minutes + 30, $reopened->balance_minutes);
    }

    public function test_migration_locks_balances_of_already_approved_months(): void {
        $this->artisan('flex:recompute')->assertExitCode(0);
        foreach ([[5, MonthClosureStatus::Approved], [6, MonthClosureStatus::Submitted]] as [$month, $status]) {
            MonthClosure::query()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'period_year' => 2030, 'period_month' => $month, 'status' => $status]);
        }

        (require database_path('migrations/2027_04_17_330400_lock_flex_balances_of_approved_months.php'))->up();

        $this->assertTrue($this->balance(2030, 5)->locked);
        $this->assertFalse($this->balance(2030, 6)->locked);
    }

    public function test_month_boundaries_follow_the_organization_timezone(): void {
        // 23:30 UTC am 31. Mai ist in Berlin schon der 1. Juni: der Juni zählt als laufender Monat.
        $this->travelTo(Carbon::parse('2030-05-31 23:30:00', 'UTC'));
        $this->organization->update(['timezone' => 'Europe/Berlin']);
        $this->user->unsetRelation('organization');

        $this->assertSame(2, app(FlexCalculator::class)->recomputeFrom($this->user->fresh(), 2030, 5));
        $this->assertSame(0, $this->balance(2030, 6)->target_minutes);
        $this->assertSame($this->balance(2030, 5)->balance_minutes, $this->balance(2030, 6)->balance_minutes);
    }

    public function test_recompute_from_ignores_future_months(): void {
        $this->assertSame(0, app(FlexCalculator::class)->recomputeFrom($this->user, 2030, 7));
        $this->assertSame(0, FlexBalance::query()->count());
    }

    /** @return array{int, int} Soll im Mai und im Juni bis gestern (Feiertage der Testregion eingerechnet) */
    private function targets(): array {
        $calculator = app(FlexCalculator::class);

        return [
            $calculator->monthlyBalance($this->user, 2030, 5)['target'],
            $calculator->monthlyBalance($this->user, 2030, 6, Carbon::parse('2030-06-11'))['target'],
        ];
    }

    private function schedule(User $user, ScheduleType $type = ScheduleType::Flextime): void {
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'schedule_type' => $type,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
    }

    private function book(string $date, int $minutes): void {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
        ]);
    }

    private function balance(int $year, int $month): FlexBalance {
        return FlexBalance::query()->where('user_id', $this->user->id)->where('year', $year)->where('month', $month)->firstOrFail();
    }
}
