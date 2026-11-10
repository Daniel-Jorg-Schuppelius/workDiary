<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Schedule\Demo;

use App\Enums\Shift\{DutyPlanPeriodType, DutyPlanStatus, ScheduledShiftStatus};
use App\Models\Platform\{Organization, User};
use App\Models\Schedule\{CoverageRequirement, DutyPlan, ScheduledShift, ShiftExchange, ShiftType};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Schedule\ShiftExchangeService;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Planung zur Vorführung (MVP-1532): drei Schichtarten, ein veröffentlichter
 * Dienstplan für diese und die nächste Woche, ein zur Genehmigung beantragter
 * Plan für die übernächste, eine Mindestbesetzung mit Lücke im Frühdienst und
 * ein angenommener Schichttausch, über den die Leitung entscheidet.
 */
final class ScheduleDemoBlock implements DemoBlock {
    public function __construct(private readonly ShiftExchangeService $exchanges) {}

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $admin = $context->users->first();
        $early = $context->users->get(1);
        $late = $context->users->get(2);
        $office = $context->users->get(3);
        if (! $context->moduleActive('module.planung') || $admin === null || $early === null || $late === null || $office === null) {
            return ['duty_plans' => 0, 'scheduled_shifts' => 0];
        }
        $organization = $context->organization;

        $earlyType = $this->shiftType($organization, $admin, 'early', 'F', '#2563eb', '06:00', '14:00');
        $lateType = $this->shiftType($organization, $admin, 'late', 'S', '#d97706', '14:00', '22:00');
        $officeType = $this->shiftType($organization, $admin, 'office', 'B', '#16a34a', '08:00', '16:30');

        $today = CarbonImmutable::now(Tz::ofOrganization($organization))->startOfDay();
        $monday = $today->startOfWeek();

        $current = $this->plan($organization, $admin, $monday, $monday->addDays(13), (string) __('demo.schedule.plan_current'));
        $shifts = 0;
        $exchangeShift = null;
        for ($day = 0; $day < 14; $day++) {
            $date = $monday->addDays($day);
            if ($date->isWeekend()) {
                continue;
            }
            $status = $date->lessThan($today) ? ScheduledShiftStatus::Confirmed : ScheduledShiftStatus::Published;
            $this->shift($current, $early, $earlyType, $date, $status, $admin);
            $lateShift = $this->shift($current, $late, $lateType, $date, $status, $admin);
            if ($date->dayOfWeekIso !== 5) {
                $this->shift($current, $office, $officeType, $date, $status, $admin);
                $shifts++;
            }
            $shifts += 2;
            if ($day === 9) {
                $exchangeShift = $lateShift;
            }
        }
        $current->update(['status' => DutyPlanStatus::Published, 'approved_at' => now(), 'approved_by' => $admin->id, 'updated_by' => $admin->id]);

        $nextMonday = $monday->addWeeks(2);
        $next = $this->plan($organization, $office, $nextMonday, $nextMonday->addDays(6), (string) __('demo.schedule.plan_next'));
        for ($day = 0; $day < 5; $day++) {
            $date = $nextMonday->addDays($day);
            $this->shift($next, $early, $earlyType, $date, ScheduledShiftStatus::Draft, $office);
            $this->shift($next, $late, $lateType, $date, ScheduledShiftStatus::Draft, $office);
            $shifts += 2;
        }
        // Eingereicht von der Disposition, damit die Leitung ihn im Arbeitsvorrat sieht.
        $next->update(['status' => DutyPlanStatus::Submitted, 'submitted_at' => now(), 'submitted_by' => $office->id, 'updated_by' => $office->id]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            CoverageRequirement::query()->create([
                'organization_id' => $organization->id,
                'shift_type_id' => $earlyType->id,
                'weekday' => $weekday,
                'min_staff' => 2,
                'ideal_staff' => 2,
                'notes' => (string) __('demo.schedule.coverage_note'),
                'created_by' => $admin->id,
            ]);
        }

        if ($exchangeShift instanceof ScheduledShift) {
            $exchange = $this->exchanges->request($exchangeShift, $late, $early, null, (string) __('demo.schedule.exchange_reason'));
            $this->exchanges->accept($exchange, $early);
        }

        return ['duty_plans' => 2, 'scheduled_shifts' => $shifts];
    }

    public function purge(Organization $organization): void {
        $shiftTypeIds = ShiftType::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->pluck('id');
        ShiftExchange::query()->withoutGlobalScopes()->withTrashed()->where('organization_id', $organization->id)->forceDelete();
        ScheduledShift::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        CoverageRequirement::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        DutyPlan::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
        DB::table('shift_type_qualifications')->whereIn('shift_type_id', $shiftTypeIds)->delete();
        ShiftType::query()->withoutGlobalScopes()->whereKey($shiftTypeIds)->delete();
    }

    private function shiftType(Organization $organization, User $admin, string $key, string $abbreviation, string $color, string $start, string $end): ShiftType {
        return ShiftType::query()->create([
            'organization_id' => $organization->id,
            'name' => (string) __('demo.schedule.shift_type.' . $key),
            'abbreviation' => $abbreviation,
            'color' => $color,
            'default_start_time' => $start,
            'default_end_time' => $end,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }

    private function plan(Organization $organization, User $author, CarbonImmutable $from, CarbonImmutable $to, string $title): DutyPlan {
        return DutyPlan::query()->create([
            'organization_id' => $organization->id,
            'title' => $title,
            'period_type' => DutyPlanPeriodType::Weekly,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'status' => DutyPlanStatus::Draft,
            'min_staff' => 2,
            'created_by' => $author->id,
        ]);
    }

    private function shift(DutyPlan $plan, User $user, ShiftType $type, CarbonImmutable $date, ScheduledShiftStatus $status, User $author): ScheduledShift {
        return ScheduledShift::query()->create([
            'organization_id' => $plan->organization_id,
            'duty_plan_id' => $plan->id,
            'user_id' => $user->id,
            'shift_type_id' => $type->id,
            'date' => $date->toDateString(),
            'status' => $status,
            'created_by' => $author->id,
            'updated_by' => $author->id,
        ]);
    }
}
