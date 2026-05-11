<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduledShiftRequest;
use App\Http\Requests\UpdateScheduledShiftRequest;
use App\Http\Resources\ScheduledShiftResource;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScheduleController extends Controller {
    public function index(Request $request, HolidayService $holidays): View {
        /** @var User $auth */
        $auth = Auth::user();

        $view     = $request->query('view', 'week'); // week|month
        $dateStr  = $request->query('date', CarbonImmutable::today()->toDateString());
        $userFilter = (int) $request->query('user', 0);

        try {
            $anchor = CarbonImmutable::parse($dateStr);
        } catch (\Exception) {
            $anchor = CarbonImmutable::today();
        }

        [$from, $to, $prevDate, $nextDate] = $this->periodBounds($anchor, $view);

        $shiftTypes = ShiftType::active()->orderBy('name')->get();
        $users      = User::orderBy('name')->get();

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

        return view('schedule.index', [
            'view'        => $view,
            'anchor'      => $anchor,
            'from'        => $from,
            'to'          => $to,
            'prevDate'    => $prevDate,
            'nextDate'    => $nextDate,
            'todayDate'   => CarbonImmutable::today()->toDateString(),
            'shifts'      => $shifts,
            'shiftsByDate'=> $shiftsByDate,
            'shiftTypes'  => $shiftTypes,
            'users'       => $users,
            'userFilter'  => $userFilter,
            'holidays'    => $holidays,
            'isAdmin'     => $auth->isAdmin(),
        ]);
    }

    // ── JSON-API for Alpine.js ───────────────────────────────────────────────

    public function apiIndex(Request $request): JsonResponse {
        $dateStr    = $request->query('date', CarbonImmutable::today()->toDateString());
        $view       = $request->query('view', 'week');
        $userFilter = (int) $request->query('user', 0);

        try {
            $anchor = CarbonImmutable::parse($dateStr);
        } catch (\Exception) {
            $anchor = CarbonImmutable::today();
        }

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
        $data['created_by'] = $auth->id;
        $data['updated_by'] = $auth->id;

        $shift = ScheduledShift::create($data);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift), 201);
    }

    public function update(UpdateScheduledShiftRequest $request, ScheduledShift $shift): JsonResponse {
        /** @var User $auth */
        $auth = Auth::user();

        $data = $request->validated();
        $data['updated_by'] = $auth->id;

        $shift->update($data);
        $shift->load(['user:id,name', 'shiftType']);

        return response()->json(new ScheduledShiftResource($shift));
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
     * @return array{CarbonImmutable, CarbonImmutable, string, string}
     */
    private function periodBounds(CarbonImmutable $anchor, string $view): array {
        if ($view === 'month') {
            $from     = $anchor->startOfMonth();
            $to       = $anchor->endOfMonth();
            $prevDate = $from->subMonth()->toDateString();
            $nextDate = $from->addMonth()->toDateString();
        } else {
            $from     = $anchor->startOfWeek(\Carbon\CarbonInterface::MONDAY);
            $to       = $from->endOfWeek(\Carbon\CarbonInterface::SUNDAY);
            $prevDate = $from->subWeek()->toDateString();
            $nextDate = $from->addWeek()->toDateString();
        }

        return [$from, $to, $prevDate, $nextDate];
    }
}
