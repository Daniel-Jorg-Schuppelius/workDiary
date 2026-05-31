<?php
/*
 * Created on   : Mon May 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScheduleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Shift\ScheduledShiftStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\{StoreScheduledShiftRequest, UpdateScheduledShiftRequest};
use App\Http\Resources\ScheduledShiftResource;
use App\Models\{Organization, ScheduledShift, ShiftType, User};
use App\Services\Compliance\ShiftComplianceService;
use App\Services\HolidayService;
use App\Services\Schedule\OpenSlotService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, HolidayService $holidays, ShiftComplianceService $compliance, OpenSlotService $openSlots): View {
        /** @var User $auth */
        $auth = Auth::user();

        $rawUserFilter = (string) $request->query('user', '');
        $userFilter = (int) (Sqid::decodeOrNumeric(User::class, $rawUserFilter, 0) ?? 0);;

        $range = $this->globalDateRange();
        $rangeFrom = $range['from'];
        $rangeTo = $range['to'];

        // Monats-Tabs: Wenn der globale Zeitraum mehr als einen Monat umfasst,
        // werden Tabs angeboten und die Matrix arbeitet nur auf dem aktiven
        // Monat. Bei ≤ 1 Monat bleibt das Verhalten wie zuvor.
        $months = $this->buildMonthsInRange($rangeFrom, $rangeTo);
        $activeMonthKey = (string) $request->query('activeMonth', '');
        $activeMonth = collect($months)->firstWhere('key', $activeMonthKey) ?? $months[0];
        $activeMonthKey = $activeMonth['key'];

        if (count($months) > 1) {
            $from = CarbonImmutable::create($activeMonth['year'], $activeMonth['month'], 1)
                ?->startOfMonth()
                ?? CarbonImmutable::now()->startOfMonth();
            $to = $from->endOfMonth();
        } else {
            $from = $rangeFrom;
            $to = $rangeTo;
        }

        $requestedView = (string) $request->query('view', '');
        $view = in_array($requestedView, ['week', 'month'], true)
            ? $requestedView
            : ($from->diffInDays($to) <= 6 ? 'week' : 'month');
        $anchor = $from;

        $shifts = $this->loadShifts($from, $to, $userFilter);
        $shiftsByDate = $shifts->groupBy(fn(ScheduledShift $s) => $s->date->toDateString());

        $org = $auth->organization_id
            ? Organization::query()->find($auth->organization_id)
            : null;
        $complianceByShift = $this->computeComplianceByShift($shifts, $compliance, $org);

        return view('schedule.index', [
            'view' => $view,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'todayDate' => CarbonImmutable::today()->toDateString(),
            'shifts' => $shifts,
            'shiftsByDate' => $shiftsByDate,
            'shiftTypes' => ShiftType::active()->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'userFilter' => $userFilter,
            'userFilterSqid' => $userFilter > 0 ? Sqid::encode(User::class, $userFilter) : null,
            'holidays' => $holidays,
            'isAdmin' => $auth->isAdmin(),
            'complianceByShift' => $complianceByShift,
            'openSlotsByDate' => $openSlots->compute($from, $to, $shifts),
            'months' => $months,
            'activeMonthKey' => $activeMonthKey,
        ]);
    }

    /**
     * @return Collection<int, ScheduledShift>
     */
    private function loadShifts(CarbonImmutable $from, CarbonImmutable $to, ?int $userFilter): Collection {
        $query = ScheduledShift::query()
            ->with(['user:id,name', 'shiftType'])
            ->forDateRange($from, $to)
            ->orderBy('date')
            ->orderBy('start_time');

        if ($userFilter !== null && $userFilter > 0) {
            $query->forUser($userFilter);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return array<int, array{severity: string, messages: list<string>}>
     */
    private function computeComplianceByShift(Collection $shifts, ShiftComplianceService $compliance, ?Organization $org): array {
        $complianceByShift = [];
        foreach ($shifts as $s) {
            $report = $compliance->check($s, $org);
            if (! $report->hasViolations()) {
                continue;
            }
            $complianceByShift[(int) $s->id] = [
                'severity' => $report->hasErrors() ? 'error' : 'warning',
                'messages' => array_map(fn($v) => $v->message, $report->violations),
            ];
        }

        return $complianceByShift;
    }

    // ── JSON-API for Alpine.js ───────────────────────────────────────────────

    public function apiIndex(Request $request): JsonResponse {
        $rawUserFilter = (string) $request->query('user', '');
        $userFilter = (int) (Sqid::decodeOrNumeric(User::class, $rawUserFilter, 0) ?? 0);

        $range = $this->globalDateRange();
        $from = $range['from'];
        $to = $range['to'];

        $query = ScheduledShift::query()
            ->with(['user:id,name', 'shiftType'])
            ->forDateRange($from, $to)
            ->orderBy('date')
            ->orderBy('start_time');
        if ($userFilter > 0) {
            $query->forUser($userFilter);
        }

        return response()->json(
            ScheduledShiftResource::collection($query->get())
        );
    }

    public function store(StoreScheduledShiftRequest $request): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        unset($data['override_compliance']);
        $data['created_by'] = $auth->id;
        $data['updated_by'] = $auth->id;

        $shift = ScheduledShift::create($data);
        $shift->load(['user:id,name', 'shiftType']);

        $payload = (new ScheduledShiftResource($shift))->resolve();
        $report = $request->complianceReport();
        if ($report && $report->hasViolations()) {
            $payload['compliance_warnings'] = $report->toArray();
        }

        return response()->json($payload, 201);
    }

    public function update(UpdateScheduledShiftRequest $request, ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        unset($data['override_compliance']);
        $data['updated_by'] = $auth->id;

        $shift->update($data);
        $shift->load(['user:id,name', 'shiftType']);

        $payload = (new ScheduledShiftResource($shift))->resolve();
        $report = $request->complianceReport();
        if ($report && $report->hasViolations()) {
            $payload['compliance_warnings'] = $report->toArray();
        }

        return response()->json($payload);
    }

    public function destroy(ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        $shift->delete();

        return response()->json(['message' => __('Schicht gelöscht.')]);
    }

    public function confirm(ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();

        if ((int) $shift->user_id !== (int) $auth->id) {
            abort(403);
        }

        if ($shift->status !== ScheduledShiftStatus::Published) {
            return response()->json(['message' => __('Nur veröffentlichte Schichten können bestätigt werden.')], 422);
        }

        $shift->update(['status' => ScheduledShiftStatus::Confirmed, 'updated_by' => $auth->id]);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift));
    }

    public function publish(ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        $shift->update(['status' => ScheduledShiftStatus::Published, 'updated_by' => $auth->id]);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

}
