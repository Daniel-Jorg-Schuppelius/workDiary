<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SicknessReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Sickness\SickLeaveKind;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\{SickLeave, User};
use App\Services\HolidayService;
use App\Services\Sickness\ContinuedPaymentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Krankheits-Report:
 * - Krankheitstage je Mitarbeiter im gewählten Zeitraum (Werktage + Kalendertage)
 * - Anzahl Krankheitsfälle / Folgebescheinigungen
 * - aktueller Lohnfortzahlungs-Status (§ 3 EntgFG)
 */
class SicknessReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly HolidayService $holidayService,
        private readonly ContinuedPaymentService $continuedPayment,
    ) {}

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        $scope = $this->resolveScope($request, $isAdmin);

        $range = $this->globalDateRange();
        $fromDate = Carbon::parse($range['from']->toDateString())->startOfDay();
        $toDate = Carbon::parse($range['to']->toDateString())->endOfDay();

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId);
        $totals = $this->totals($rows);

        return view('reports.sickness', [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    private function resolveScope(Request $request, bool $isAdmin): string {
        $scope = $request->string('scope', 'mine')->toString();
        if ($scope !== 'team' || ! $isAdmin) {
            $scope = 'mine';
        }

        return $scope;
    }

    /**
     * @return array<int, array{
     *   user: User,
     *   sick_workdays:int,
     *   sick_calendar_days:int,
     *   episodes:int,
     *   follow_ups:int,
     *   with_au:int,
     *   entitlement_days:int,
     *   used_days:int,
     *   remaining_days:int,
     *   exhausted:bool,
     *   chain_start:?string,
     *   exhaustion_date:?string
     * }>
     */
    private function aggregate(Carbon $from, Carbon $to, string $scope, int $userId): array {
        $q = SickLeave::query()
            ->whereNull('cancelled_at')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString());
        if ($scope === 'mine') {
            $q->where('user_id', $userId);
        }
        /** @var Collection<int, SickLeave> $leaves */
        $leaves = $q->with('attachments')->get();

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
            $byUser[$uid]['workdays'] += $this->countWorkdays($start, $end);
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
            $uid = (int) $user->id;
            $data = $byUser[$uid];
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
     * @param  array<int, array{user:User, sick_workdays:int, sick_calendar_days:int, episodes:int, follow_ups:int, with_au:int, entitlement_days:int, used_days:int, remaining_days:int, exhausted:bool, chain_start:?string, exhaustion_date:?string}>  $rows
     * @return array{users:int, sick_workdays:int, sick_calendar_days:int, episodes:int, follow_ups:int, with_au:int, exhausted:int}
     */
    private function totals(array $rows): array {
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

    private function countWorkdays(Carbon $start, Carbon $end): int {
        if ($start->greaterThan($end)) {
            return 0;
        }
        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();
        while ($cursor->lte($endDay)) {
            if ($cursor->isWeekday() && ! $this->holidayService->isHoliday($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
