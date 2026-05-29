<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Diary\{Mode, Status as DiaryStatus};
use App\Enums\Tour\TourStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveTourRequest;
use App\Models\{DiaryEntry, Tour, User, Vehicle};
use App\Services\Routing\TourService;
use App\Support\{Setting, SortableQuery};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TourController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly TourService $tours) {
    }

    public function index(Request $request): View {
        Gate::authorize('viewAny', Tour::class);

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        $query = Tour::query()
            ->with(['user:id,name', 'vehicle:id,license_plate,label'])
            ->whereBetween('tour_date', [$from->toDateTimeString(), $to->toDateTimeString()]);

        if ($target !== null) {
            $query->where('user_id', $target->id);
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'tour_date' => 'tour_date',
            'name' => 'name',
            'distance' => 'planned_distance_km',
            'duration' => 'planned_duration_minutes',
            'status' => 'status',
        ], 'tour_date', 'desc');

        $tours = $query->paginate((int) Setting::get('pagination.tours', 25))->withQueryString();

        return view('tours.index', [
            'tours' => $tours,
            'from' => $from,
            'to' => $to,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'statuses' => TourStatus::options(),
            'selectedStatus' => $request->query('status'),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', Tour::class);

        return view('tours._form_dialog', [
            'tour' => null,
            'date' => $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
            'users' => $this->loadSelectableUsers(),
            'vehicles' => $this->loadVehicles(),
            'statuses' => TourStatus::options(),
        ]);
    }

    public function store(SaveTourRequest $request): RedirectResponse {
        Gate::authorize('create', Tour::class);

        /** @var User $auth */
        $auth = Auth::user();
        $data = $request->validated();

        $driver = User::query()->findOrFail((int) $data['user_id']);
        if (! $auth->isAdmin() && (int) $driver->id !== (int) $auth->id) {
            throw new AccessDeniedHttpException('Nur Admins dürfen Touren für andere Nutzer anlegen.');
        }

        $tour = $this->tours->createDraft(
            $driver,
            CarbonImmutable::parse((string) $data['tour_date']),
            [],
        );
        $tour->fill(array_intersect_key($data, array_flip([
            'vehicle_id',
            'name',
            'start_address',
            'start_lat',
            'start_lng',
            'end_address',
            'end_lat',
            'end_lng',
            'notes',
        ])));
        $tour->save();

        return redirect()->route('tours.edit', $tour)
            ->with('success', __('Tour angelegt.'));
    }

    public function show(Tour $tour): View {
        Gate::authorize('view', $tour);

        $tour->load([
            'user:id,name',
            'vehicle:id,license_plate,label',
            'diaryEntries' => fn($q) => $q->orderByRaw('tour_position IS NULL')->orderBy('tour_position'),
            'diaryEntries.customer:id,name',
        ]);

        return view('tours.show', ['tour' => $tour]);
    }

    public function edit(Tour $tour): View {
        Gate::authorize('update', $tour);

        $tour->load([
            'diaryEntries' => fn($q) => $q->orderByRaw('tour_position IS NULL')->orderBy('tour_position'),
            'diaryEntries.customer:id,name',
        ]);

        $tourDate = $tour->tour_date?->toDateString() ?? CarbonImmutable::today()->toDateString();

        // Fix terminierte Aufträge für diesen Tag (heutiges Verhalten).
        $available = DiaryEntry::query()
            ->whereNull('tour_id')
            ->whereHas('entryType', fn($q) => $q->where('allow_tour', true))
            ->whereDate('scheduled_for', $tourDate)
            ->orderBy('time_window_start')
            ->orderBy('id')
            ->get();

        // Flex-Backlog: Aufträge ohne festen Termin, deren Modus den Tour-Tag
        // sinnvoll trifft (Deadline noch nicht überschritten / Tag im Fenster
        // / Backlog jederzeit / Wiederkehr fällig). Tour-Planer kann sie als
        // Lückenfüller einplanen — `service_minutes` hilft beim Auswählen.
        $flexBacklog = DiaryEntry::query()
            ->whereNull('tour_id')
            ->whereIn('status', [DiaryStatus::Open->value, DiaryStatus::Problem->value])
            ->where('is_archived', false)
            ->whereHas('entryType', fn($q) => $q->where('allow_tour', true))
            ->where(function ($q) use ($tourDate): void {
                $q->where(function ($sub) use ($tourDate): void {
                    $sub->where('mode', Mode::Deadline->value)
                        ->whereDate('due_date', '>=', $tourDate);
                });
                $q->orWhere(function ($sub) use ($tourDate): void {
                    $sub->where('mode', Mode::Window->value)
                        ->whereDate('window_start_date', '<=', $tourDate)
                        ->whereDate('window_end_date', '>=', $tourDate);
                });
                $q->orWhere('mode', Mode::Backlog->value);
                $q->orWhere(function ($sub) use ($tourDate): void {
                    $sub->where('mode', Mode::Recurring->value)
                        ->whereDate('due_date', '<=', $tourDate);
                });
            })
            ->orderByRaw('service_minutes IS NULL')
            ->orderBy('service_minutes')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return view('tours.edit', [
            'tour' => $tour,
            'available' => $available,
            'flexBacklog' => $flexBacklog,
            'users' => $this->loadSelectableUsers(),
            'vehicles' => $this->loadVehicles(),
            'statuses' => TourStatus::options(),
        ]);
    }

    public function update(SaveTourRequest $request, Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);

        $data = $request->validated();
        $tour->fill($data)->save();

        $orderIds = $request->input('order_ids', []);
        if (is_array($orderIds)) {
            $ids = array_values(array_map(static fn($id) => (int) $id, $orderIds));
            $this->tours->assignOrders($tour, $ids);
        }

        return redirect()->route('tours.edit', $tour)
            ->with('success', __('Tour aktualisiert.'));
    }

    public function destroy(Tour $tour): RedirectResponse {
        Gate::authorize('delete', $tour);

        DiaryEntry::query()
            ->where('tour_id', $tour->id)
            ->update(['tour_id' => null, 'tour_position' => null, 'status' => DiaryStatus::Open->value]);
        $tour->delete();

        return redirect()->route('tours.index')
            ->with('success', __('Tour gelöscht.'));
    }

    public function optimize(Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);

        $result = $this->tours->recalculate($tour);

        return redirect()->route('tours.edit', $tour)
            ->with('success', __('Tour optimiert (:km km, :min min).', [
                'km' => number_format($result['distance_km'], 2, ',', '.'),
                'min' => $result['duration_minutes'],
            ]));
    }

    public function start(Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);
        try {
            $this->tours->start($tour);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Tour gestartet.'));
    }

    public function complete(Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);
        try {
            $this->tours->complete($tour);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('success', __('Tour abgeschlossen.'));
    }

    public function materialize(Tour $tour): RedirectResponse {
        Gate::authorize('update', $tour);

        $logs = $this->tours->materializeToTravelLogs($tour);

        return redirect()->route('tours.show', $tour)
            ->with('success', __(':count Fahrten erzeugt.', ['count' => count($logs)]));
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

    private function resolveTargetUser(Request $request, User $authUser): ?User {
        if (! $request->filled('user')) {
            return $authUser;
        }

        $raw = (string) $request->query('user');
        if ($raw === 'all') {
            if (! $authUser->isAdmin()) {
                throw new AccessDeniedHttpException('Nur Admins dürfen alle Touren einsehen.');
            }

            return null;
        }

        $requestedId = Sqid::decode(User::class, $raw);
        if ($requestedId === null || $requestedId === (int) $authUser->id) {
            return $authUser;
        }
        if (! $authUser->isAdmin()) {
            throw new AccessDeniedHttpException('Nur Admins dürfen fremde Touren einsehen.');
        }

        $target = User::query()->find($requestedId);
        if (! $target instanceof User) {
            throw new AccessDeniedHttpException('Nutzer nicht gefunden.');
        }

        return $target;
    }

    /** @return Collection<int, User> */
    private function loadSelectableUsers(): Collection {
        /** @var Collection<int, User> $users */
        $users = User::query()->orderBy('name')->get(['id', 'name']);

        return $users;
    }

    /** @return Collection<int, Vehicle> */
    private function loadVehicles(): Collection {
        /** @var Collection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()->active()->orderBy('label')->get(['id', 'license_plate', 'label']);

        return $vehicles;
    }
}
