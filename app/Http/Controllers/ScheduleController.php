<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\StoreScheduledShiftRequest;
use App\Http\Requests\UpdateScheduledShiftRequest;
use App\Http\Resources\ScheduledShiftResource;
use App\Models\Organization;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\Compliance\ShiftComplianceService;
use App\Services\HolidayService;
use App\Services\Schedule\OpenSlotService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request, HolidayService $holidays, ShiftComplianceService $compliance, OpenSlotService $openSlots): View {
        /** @var User $auth */
        $auth = Auth::user();

        $view = $request->query('view', 'week'); // week|month
        $userFilter = (int) $request->query('user', 0);

        $anchor = $this->globalDateRange()['from'];

        [$from, $to] = $this->periodBounds($anchor, $view);

        $shiftTypes = ShiftType::active()->orderBy('name')->get();
        $users = User::orderBy('name')->get();

        $shiftsQuery = ScheduledShift::query()
            ->with(['user:id,name', 'shiftType'])
            ->forDateRange($from, $to)
            ->orderBy('date')
            ->orderBy('start_time');

        if ($userFilter > 0) {
            $shiftsQuery->forUser($userFilter);
        }

        $shifts = $shiftsQuery->get();

        // Group by date string for template
        $shiftsByDate = $shifts->groupBy(fn(ScheduledShift $s) => $s->date->toDateString());

        // Compliance-Reports pro Schicht (für visuelle Markierung in der Matrix)
        $org = $auth->organization_id
            ? Organization::query()->find($auth->organization_id)
            : null;
        /** @var array<int, array{severity: string, messages: list<string>}> $complianceByShift */
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

        return view('schedule.index', [
            'view' => $view,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
            'todayDate' => CarbonImmutable::today()->toDateString(),
            'shifts' => $shifts,
            'shiftsByDate' => $shiftsByDate,
            'shiftTypes' => $shiftTypes,
            'users' => $users,
            'userFilter' => $userFilter,
            'holidays' => $holidays,
            'isAdmin' => $auth->isAdmin(),
            'complianceByShift' => $complianceByShift,
            'openSlotsByDate' => $openSlots->compute($from, $to, $shifts),
        ]);
    }

    // ── JSON-API for Alpine.js ───────────────────────────────────────────────

    public function apiIndex(Request $request): JsonResponse {
        $view = $request->query('view', 'week');
        $userFilter = (int) $request->query('user', 0);

        $anchor = $this->globalDateRange()['from'];

        [$from, $to] = $this->periodBounds($anchor, $view);

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

        if ($shift->status !== ScheduledShift::STATUS_PUBLISHED) {
            return response()->json(['message' => __('Nur veröffentlichte Schichten können bestätigt werden.')], 422);
        }

        $shift->update(['status' => ScheduledShift::STATUS_CONFIRMED, 'updated_by' => $auth->id]);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift));
    }

    public function publish(ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();
        if (! $auth->isAdmin()) {
            abort(403);
        }

        $shift->update(['status' => ScheduledShift::STATUS_PUBLISHED, 'updated_by' => $auth->id]);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function periodBounds(CarbonImmutable $anchor, string $view): array {
        if ($view === 'month') {
            $from = $anchor->startOfMonth();
            $to = $anchor->endOfMonth();
        } else {
            $from = $anchor->startOfWeek(CarbonInterface::MONDAY);
            $to = $from->endOfWeek(CarbonInterface::SUNDAY);
        }

        return [$from, $to];
    }
}
