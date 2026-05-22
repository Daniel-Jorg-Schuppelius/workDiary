<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemTripController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Expense\PerDiemTripStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SavePerDiemDayRequest;
use App\Http\Requests\SavePerDiemTripRequest;
use App\Models\Customer;
use App\Models\PerDiemDay;
use App\Models\PerDiemTrip;
use App\Models\Project;
use App\Models\TravelLog;
use App\Models\User;
use App\Services\Expense\PerDiemEligibilityChecker;
use App\Services\Expense\PerDiemTripService;
use App\Support\SortableQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PerDiemTripController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly PerDiemTripService $service,
        private readonly PerDiemEligibilityChecker $eligibility,
    ) {
    }

    public function index(Request $request): View {
        Gate::authorize('viewAny', PerDiemTrip::class);

        [$from, $to] = $this->resolveRange($request);

        $statusFilter = $request->string('status')->toString();
        $statusEnum = $statusFilter !== '' ? PerDiemTripStatus::tryFrom($statusFilter) : null;

        $query = PerDiemTrip::query()
            ->with(['project:id,name', 'customer:id,name', 'days'])
            ->where('user_id', Auth::id())
            ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()]);

        if ($statusEnum !== null) {
            $query->where('status', $statusEnum->value);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'started_at' => 'started_at',
            'location' => 'location',
            'status' => 'status',
        ], 'started_at', 'desc');

        $trips = $query->paginate(25)->withQueryString();

        $totals = [
            'count' => (int) PerDiemTrip::query()
                ->where('user_id', Auth::id())
                ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])
                ->count(),
            'amount' => (float) PerDiemTrip::query()
                ->where('user_id', Auth::id())
                ->whereBetween('started_at', [$from->startOfDay(), $to->endOfDay()])
                ->withSum('days as days_amount_sum', 'amount')
                ->get()
                ->sum('days_amount_sum'),
            'open' => (int) PerDiemTrip::query()
                ->where('user_id', Auth::id())
                ->where('status', PerDiemTripStatus::Draft->value)
                ->count(),
        ];

        return view('per-diem-trips.index', [
            'trips' => $trips,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'sort' => $sort,
            'dir' => $dir,
            'statusFilter' => $statusFilter,
            'statusOptions' => PerDiemTripStatus::cases(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', PerDiemTrip::class);

        return view('per-diem-trips._form_dialog', [
            'trip' => null,
            'date' => CarbonImmutable::today()->toDateString(),
            'eligibility' => null,
        ] + $this->formData());
    }

    public function store(SavePerDiemTripRequest $request): RedirectResponse {
        Gate::authorize('create', PerDiemTrip::class);

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        /** @var User $user */
        $user = Auth::user();
        $data['organization_id'] = $user->organization_id;

        $trip = $this->service->create($data);

        return redirect()->route('per-diem-trips.show', $trip)
            ->with('success', __('Reise angelegt.'));
    }

    public function show(PerDiemTrip $perDiemTrip): View {
        Gate::authorize('view', $perDiemTrip);

        $perDiemTrip->load(['days', 'project:id,name', 'customer:id,name', 'expense:id,status', 'travelLog:id,started_at,ended_at']);
        $eligibility = $this->eligibility->check($perDiemTrip);

        return view('per-diem-trips.show', [
            'trip' => $perDiemTrip,
            'eligibility' => $eligibility,
        ]);
    }

    public function edit(PerDiemTrip $perDiemTrip): View {
        Gate::authorize('update', $perDiemTrip);

        return view('per-diem-trips._form_dialog', [
            'trip' => $perDiemTrip,
            'date' => $perDiemTrip->started_at->toDateString(),
            'eligibility' => $this->eligibility->check($perDiemTrip),
        ] + $this->formData());
    }

    public function update(SavePerDiemTripRequest $request, PerDiemTrip $perDiemTrip): RedirectResponse {
        Gate::authorize('update', $perDiemTrip);

        $this->service->update($perDiemTrip, $request->validated());

        return redirect()->route('per-diem-trips.show', $perDiemTrip)
            ->with('success', __('Reise aktualisiert.'));
    }

    public function destroy(PerDiemTrip $perDiemTrip): RedirectResponse {
        Gate::authorize('delete', $perDiemTrip);

        $this->service->delete($perDiemTrip);

        return redirect()->route('per-diem-trips.index')
            ->with('success', __('Reise gelöscht.'));
    }

    public function updateDay(SavePerDiemDayRequest $request, PerDiemTrip $perDiemTrip, PerDiemDay $day): RedirectResponse {
        Gate::authorize('update', $perDiemTrip);

        abort_unless($day->per_diem_trip_id === $perDiemTrip->id, 404);

        $this->service->updateDay($day, $request->validated());

        return redirect()->route('per-diem-trips.show', $perDiemTrip)
            ->with('success', __('Tag aktualisiert.'));
    }

    public function convert(PerDiemTrip $perDiemTrip): RedirectResponse {
        Gate::authorize('convert', $perDiemTrip);

        $expense = $this->service->convertToExpense($perDiemTrip);

        return redirect()->route('expenses.index')
            ->with('success', __('Spese :id aus Reise erzeugt und zur Genehmigung eingereicht.', ['id' => $expense->id]));
    }

    public function cancel(PerDiemTrip $perDiemTrip): RedirectResponse {
        Gate::authorize('cancel', $perDiemTrip);

        $this->service->cancel($perDiemTrip);

        return redirect()->route('per-diem-trips.index')
            ->with('success', __('Reise storniert.'));
    }

    public function fromTravelLog(TravelLog $travelLog): RedirectResponse {
        Gate::authorize('create', PerDiemTrip::class);
        abort_unless($travelLog->user_id === Auth::id(), 403);

        $trip = $this->service->createFromTravelLog($travelLog);

        return redirect()->route('per-diem-trips.edit', $trip)
            ->with('success', __('Verpflegungspauschale aus Fahrt erzeugt – bitte Mahlzeiten prüfen.'));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array {
        if ($request->filled('from') && $request->filled('to')) {
            $from = CarbonImmutable::parse((string) $request->query('from'))->startOfDay();
            $to = CarbonImmutable::parse((string) $request->query('to'))->endOfDay();

            return [$from, $to];
        }

        $range = $this->globalDateRange();

        return [$range['from']->startOfDay(), $range['to']->endOfDay()];
    }

    /** @return array<string, mixed> */
    private function formData(): array {
        $userId = Auth::id();

        return [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'travelLogs' => TravelLog::query()
                ->where('user_id', $userId)
                ->orderByDesc('started_at')
                ->limit(50)
                ->get(['id', 'started_at', 'ended_at', 'from_address', 'to_address']),
            'countries' => ['DE'],
        ];
    }
}
