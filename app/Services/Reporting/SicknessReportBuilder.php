<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SicknessReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\Sickness\SickLeaveKind;
use App\Models\Absence\SickLeave;
use App\Models\Platform\User;
use App\Services\Calendar\HolidayService;
use App\Services\Sickness\ContinuedPaymentService;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Collection;

/**
 * Rechenweg der Krankheits-Auswertung: Krankheitstage (Werktage und
 * Kalendertage), Fälle, Folgebescheinigungen und Entgeltfortzahlung je
 * Person. Bericht und Leitungssicht (MVP-1617) rechnen hier.
 *
 * @phpstan-type SicknessRow array{user: User, sick_workdays: int, sick_calendar_days: int, episodes: int, follow_ups: int, with_au: int, entitlement_days: int, used_days: int, remaining_days: int, exhausted: bool, chain_start: ?string, exhaustion_date: ?string}
 * @phpstan-type SicknessTotals array{users: int, sick_workdays: int, sick_calendar_days: int, episodes: int, follow_ups: int, with_au: int, exhausted: int}
 */
final class SicknessReportBuilder {
    public function __construct(
        private readonly HolidayService $holidays,
        private readonly ContinuedPaymentService $continuedPayment,
    ) {}

    /**
     * Krankmeldungen, die den Zeitraum berühren; ohne stornierte.
     *
     * @return Collection<int, SickLeave>
     */
    public function leaves(CarbonImmutable $from, CarbonImmutable $to, ?int $onlyUserId = null, ?ReportFilters $filters = null, ?int $organizationId = null): Collection {
        $q = SickLeave::query()
            ->whereNull('cancelled_at')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<', DateRange::dayAfter($to))
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->when($onlyUserId !== null, fn ($q) => $q->where('user_id', $onlyUserId));
        $filters?->applyUserAndTeam($q);

        /** @var Collection<int, SickLeave> $leaves */
        $leaves = $q->with('attachments')->get();

        return $leaves;
    }

    /**
     * @param  Collection<int, SickLeave>  $leaves
     * @return list<SicknessRow>
     */
    public function rows(CarbonImmutable $from, CarbonImmutable $to, Collection $leaves): array {
        /** @var array<int, array{workdays:int, cal:int, episodes:int, follow:int, with_au:int}> $byUser */
        $byUser = [];
        foreach ($leaves as $s) {
            $uid = (int) $s->user_id;
            if (! isset($byUser[$uid])) {
                $byUser[$uid] = ['workdays' => 0, 'cal' => 0, 'episodes' => 0, 'follow' => 0, 'with_au' => 0];
            }
            $start = $s->start_date->greaterThan($from) ? $s->start_date->copy() : $from->copy();
            $end = $s->end_date->lessThan($to) ? $s->end_date->copy() : $to->copy();
            if ($start->greaterThan($end)) {
                continue;
            }
            $byUser[$uid]['workdays'] += $this->workdays($start, $end);
            $byUser[$uid]['cal'] += (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
            $byUser[$uid]['episodes']++;
            if ($s->kind === SickLeaveKind::FollowUp) {
                $byUser[$uid]['follow']++;
            }
            if ($s->attachments->isNotEmpty()) {
                $byUser[$uid]['with_au']++;
            }
        }

        if ($byUser === []) {
            return [];
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', array_keys($byUser))->orderBy('name')->get();

        $rows = [];
        foreach ($users as $user) {
            $data = $byUser[(int) $user->id];
            $status = $this->continuedPayment->statusFor($user);
            $rows[] = [
                'user' => $user,
                'sick_workdays' => $data['workdays'],
                'sick_calendar_days' => $data['cal'],
                'episodes' => $data['episodes'],
                'follow_ups' => $data['follow'],
                'with_au' => $data['with_au'],
                'entitlement_days' => $status->entitlementDays,
                'used_days' => $status->usedDays,
                'remaining_days' => max(0, $status->remainingDays),
                'exhausted' => $status->exhausted,
                'chain_start' => $status->chainStart?->toDateString(),
                'exhaustion_date' => $status->exhaustionDate?->toDateString(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<SicknessRow>  $rows
     * @return SicknessTotals
     */
    public function totals(array $rows): array {
        $t = [
            'users' => count($rows),
            'sick_workdays' => 0,
            'sick_calendar_days' => 0,
            'episodes' => 0,
            'follow_ups' => 0,
            'with_au' => 0,
            'exhausted' => 0,
        ];
        foreach ($rows as $r) {
            $t['sick_workdays'] += $r['sick_workdays'];
            $t['sick_calendar_days'] += $r['sick_calendar_days'];
            $t['episodes'] += $r['episodes'];
            $t['follow_ups'] += $r['follow_ups'];
            $t['with_au'] += $r['with_au'];
            if ($r['exhausted']) {
                $t['exhausted']++;
            }
        }

        return $t;
    }

    public function workdays(CarbonInterface $start, CarbonInterface $end): int {
        return $start->greaterThan($end) ? 0 : $this->holidays->workingDaysBetween($start, $end);
    }
}
