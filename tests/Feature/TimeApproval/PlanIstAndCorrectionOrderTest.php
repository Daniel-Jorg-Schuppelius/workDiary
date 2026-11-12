<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlanIstAndCorrectionOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\Attendance\AttendanceStatus;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use App\Models\Time\{Attendance, TimeEntry, WorkSchedule};
use App\Services\Reporting\PlanIstReportBuilder;
use App\Services\TimeApproval\{TimeCorrectionService, TimeCorrectionWorkflowException};
use App\Support\MorphMap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1552: Homeoffice/Dienstgang sichtbar, Auftrag per Korrektur ändern, Plan/Ist am Tag und am Auftrag. */
class PlanIstAndCorrectionOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string DAY = '2030-01-15';

    private User $user;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2030-01-16 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
        $this->user = $this->orgUser();
        $this->project = Project::factory()->create(['organization_id' => $this->organization->id]);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'core_start' => '09:00',
            'core_end' => '15:00',
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
    }

    public function test_attendance_list_report_and_csv_show_homeoffice_and_errand_minutes(): void {
        $this->attendance('09:00', '17:00', ['homeoffice_minutes' => 125, 'errand_minutes' => 40]);

        $this->actingAs($this->user)
            ->get(route('attendance.index', ['from' => self::DAY, 'to' => self::DAY]))
            ->assertOk()
            ->assertSee(__('attendance.intermediate.homeoffice'))
            ->assertSee('2:05')
            ->assertSee('0:40');

        $this->actingAs($this->user)
            ->get(route('today.show', ['date' => self::DAY]))
            ->assertOk()
            ->assertSee(__('attendance.intermediate.errand'));

        $report = $this->actingAs($this->user)->get(route('reports.attendance', ['from' => self::DAY, 'to' => self::DAY]));
        $report->assertOk()->assertSee(__('attendance.intermediate.homeoffice'));
        $rows = collect($report->viewData('rows'))->firstWhere('user.id', $this->user->id);
        $this->assertSame(125, $rows['homeoffice_minutes']);
        $this->assertSame(40, $rows['errand_minutes']);
        $this->assertSame(125, $report->viewData('totals')['homeoffice']);

        $csv = $this->actingAs($this->user)->get(route('reports.attendance', ['from' => self::DAY, 'to' => self::DAY, 'export' => 'csv']));
        $csv->assertOk();
        $content = (string) $csv->getContent();
        $this->assertStringContainsString(__('reporting.csv.homeoffice_minutes'), $content);
        $this->assertStringContainsString('125', $content);
    }

    public function test_correction_changes_the_order_of_a_booking(): void {
        [$entry, $other] = $this->entryWithOrders();
        $service = app(TimeCorrectionService::class);
        $admin = $this->orgUser();

        $request = $service->createDraft($this->user, CarbonImmutable::parse(self::DAY), str_repeat('A', 30), [[
            'target_type' => MorphMap::alias(TimeEntry::class),
            'target_id' => $entry->id,
            'action' => 'update',
            'after' => ['diary_entry_id' => $other->id],
        ]], $this->user);
        $service->apply($service->approve($service->submit($request, $this->user), $admin));

        $this->assertSame($other->id, (int) $entry->fresh()?->diary_entry_id);
    }

    public function test_correction_rejects_an_order_of_another_organization(): void {
        [$entry] = $this->entryWithOrders();
        $foreign = DiaryEntry::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        try {
            app(TimeCorrectionService::class)->createDraft($this->user, CarbonImmutable::parse(self::DAY), str_repeat('A', 30), [[
                'target_type' => MorphMap::alias(TimeEntry::class),
                'target_id' => $entry->id,
                'action' => 'update',
                'after' => ['diary_entry_id' => $foreign->id],
            ]], $this->user);
            $this->fail('Erwartete itemForeignReference.');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('itemForeignReference', $e->reasonCode);
        }
    }

    public function test_dialog_offers_orders_and_store_takes_the_choice(): void {
        [$entry, $other] = $this->entryWithOrders();

        $targets = $this->actingAs($this->user)->getJson(route('corrections.targets', ['date' => self::DAY]))->assertOk()->json(MorphMap::alias(TimeEntry::class));
        $this->assertSame($entry->sqid, $targets[0]['id']);
        $this->assertContains($other->sqid, array_column($targets[0]['orders'], 'id'));
        $this->assertNotSame('', $targets[0]['order']);

        $this->actingAs($this->user)->post(route('corrections.store'), [
            'scope_date' => self::DAY,
            'reason' => 'Die Zeit gehört zum anderen Auftrag des Projekts.',
            'items' => [[
                'target_type' => MorphMap::alias(TimeEntry::class),
                'target_id' => $entry->sqid,
                'action' => 'update',
                'diary_entry' => $other->sqid,
            ]],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertDatabaseHas('time_correction_items', ['target_id' => $entry->id]);
        $item = \App\Models\Time\TimeCorrectionItem::query()->latest('id')->firstOrFail();
        $this->assertSame($other->id, (int) ($item->after['diary_entry_id'] ?? 0));
    }

    public function test_plan_ist_thresholds_come_from_the_organization_settings(): void {
        $this->attendance('09:40', '17:40');
        $day = CarbonImmutable::parse(self::DAY);
        $builder = app(PlanIstReportBuilder::class);

        $row = $builder->presenceFor($this->user, $day, $day)[0];
        $this->assertContains('presence.lateStart', $row['warnings']);
        $this->assertSame('17:40', $row['actual_end']);

        $this->organization->update(['settings' => ['plan_ist' => ['late_start_minutes' => 60, 'hours_diff_percent' => 50]]]);
        app()->instance('currentOrganization', $this->organization->fresh());

        $this->assertSame(60, PlanIstReportBuilder::lateStartMinutes());
        $this->assertSame([], $builder->presenceFor($this->user, $day, $day)[0]['warnings']);
    }

    public function test_day_head_shows_plan_and_actual_with_warning(): void {
        $this->attendance('09:40', '17:40');

        $this->actingAs($this->user)
            ->get(route('today.show', ['date' => self::DAY]))
            ->assertOk()
            ->assertSee('data-plan-ist', false)
            ->assertSee(__('day-close.plan_ist.plan', ['start' => '09:00', 'duration' => '8:00 h']))
            ->assertSee(__('reporting.plan_ist.warning.presence.lateStart', ['minutes' => 15, 'percent' => 10]));
    }

    public function test_order_page_shows_plan_and_actual(): void {
        [$entry] = $this->entryWithOrders();
        $order = DiaryEntry::query()->findOrFail($entry->diary_entry_id);
        $order->update(['planned_minutes' => 120]);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('diary.show', $order))
            ->assertOk()
            ->assertSee(__('diary.plan_actual.label'))
            ->assertSeeInOrder(['2:00', '1:30']);
    }

    /** @param  array<string, mixed>  $extra */
    private function attendance(string $from, string $to, array $extra = []): Attendance {
        return Attendance::withoutEvents(fn (): Attendance => Attendance::query()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => self::DAY,
            'started_at' => self::DAY . ' ' . $from . ':00',
            'ended_at' => self::DAY . ' ' . $to . ':00',
            'duration_minutes' => 480,
            'status' => AttendanceStatus::Closed,
        ] + $extra));
    }

    /** @return array{TimeEntry, DiaryEntry} Buchung am ersten Auftrag und ein zweiter Auftrag desselben Projekts */
    private function entryWithOrders(): array {
        $orders = DiaryEntry::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
        ]);
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $orders[0]->id,
            'date' => self::DAY,
            'minutes' => 90,
        ]);

        return [$entry, $orders[1]];
    }
}
