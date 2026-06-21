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
use App\Http\Controllers\Concerns\{ResolvesGlobalDateRange, ResolvesRequestedUser};
use App\Http\Requests\SaveTourRequest;
use App\Models\{Customer, DiaryEntry, Site, Tour, User, Vehicle};
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
    use ResolvesGlobalDateRange, ResolvesRequestedUser;

    public function __construct(private readonly TourService $tours) {}

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
     * Übersichtskarte: alle Touren eines Zeitraums (farbige Routen + Stopps),
     * offene Aufträge mit Koordinaten sowie zuschaltbare Stammdaten-Layer
     * (Kunden, Standorte, Fahrer-Zuhause).
     */
    public function map(Request $request): View {
        Gate::authorize('viewAny', Tour::class);

        /** @var User $auth */
        $auth = Auth::user();
        $target = $this->resolveTargetUser($request, $auth);
        [$from, $to] = $this->resolveRange($request);

        // Farbpalette für Touren (zyklisch).
        $palette = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#65a30d'];

        $tourQuery = Tour::query()
            ->with(['user:id,name', 'orderedStops:id,tour_id,tour_position,title,address_lat,address_lng,address_city'])
            ->whereBetween('tour_date', [$from->toDateTimeString(), $to->toDateTimeString()]);
        if ($target !== null) {
            $tourQuery->where('user_id', $target->id);
        }
        if ($request->filled('status')) {
            $tourQuery->where('status', (string) $request->query('status'));
        }
        /** @var Collection<int, Tour> $tours */
        $tours = $tourQuery->orderBy('tour_date')->get();

        $routes = [];
        $markers = [];
        foreach ($tours as $i => $tour) {
            $color = $palette[$i % count($palette)];
            $label = ($tour->name ?? ('#' . $tour->id)) . ' · ' . ($tour->user->name ?? '');

            $geometry = $tour->geometryArray();
            if ($geometry !== null) {
                $routes[] = ['geometry' => $geometry, 'color' => $color, 'label' => $label, 'layer' => 'tours'];
            }

            if ($tour->start_lat !== null && $tour->start_lng !== null) {
                $markers[] = [
                    'lat' => (float) $tour->start_lat,
                    'lng' => (float) $tour->start_lng,
                    'label' => __('Start') . ' · ' . $label,
                    'layer' => 'tours',
                    'color' => $color,
                ];
            }
            foreach ($tour->orderedStops as $pos => $stop) {
                if ($stop->address_lat === null || $stop->address_lng === null) {
                    continue;
                }
                $markers[] = [
                    'lat' => (float) $stop->address_lat,
                    'lng' => (float) $stop->address_lng,
                    'label' => ($stop->tour_position ?? ($pos + 1)) . '. ' . $stop->title,
                    'popup' => $label . '<br>' . e((string) $stop->title) . '<br>' . e((string) $stop->address_city),
                    'layer' => 'tours',
                    'color' => $color,
                ];
            }
        }

        // Offene, nicht zugewiesene Aufträge mit Koordinaten.
        $openQuery = DiaryEntry::query()
            ->whereNull('tour_id')
            ->whereNotNull('address_lat')
            ->whereNotNull('address_lng')
            ->whereIn('status', [DiaryStatus::Open->value, DiaryStatus::Problem->value])
            ->where('is_archived', false)
            ->whereBetween('scheduled_for', [$from->toDateString(), $to->toDateString()]);
        foreach ($openQuery->limit(500)->get(['id', 'title', 'address_lat', 'address_lng', 'address_city']) as $entry) {
            $markers[] = [
                'lat' => (float) $entry->address_lat,
                'lng' => (float) $entry->address_lng,
                'label' => $entry->title,
                'layer' => 'open',
                'color' => '#f59e0b',
                'popup' => __('Offener Auftrag') . '<br>' . e((string) $entry->title),
            ];
        }

        // Stammdaten-Layer (zuschaltbar).
        foreach (Customer::query()->whereNotNull('address_lat')->whereNotNull('address_lng')->get(['id', 'name', 'address_lat', 'address_lng']) as $customer) {
            $markers[] = [
                'lat' => (float) $customer->address_lat,
                'lng' => (float) $customer->address_lng,
                'label' => $customer->name,
                'layer' => 'customers',
                'color' => '#0d9488',
            ];
        }
        foreach (Site::query()->whereNotNull('geo_lat')->whereNotNull('geo_lng')->get(['id', 'name', 'geo_lat', 'geo_lng']) as $site) {
            $markers[] = [
                'lat' => (float) $site->geo_lat,
                'lng' => (float) $site->geo_lng,
                'label' => $site->name,
                'layer' => 'sites',
                'color' => '#9333ea',
            ];
        }
        foreach (User::query()->whereNotNull('home_lat')->whereNotNull('home_lng')->get(['id', 'name', 'home_lat', 'home_lng']) as $driver) {
            $markers[] = [
                'lat' => (float) $driver->home_lat,
                'lng' => (float) $driver->home_lng,
                'label' => __('Zuhause') . ' · ' . $driver->name,
                'layer' => 'drivers',
                'color' => '#475569',
            ];
        }

        $layers = [
            ['key' => 'tours', 'label' => __('Touren'), 'color' => '#2563eb'],
            ['key' => 'open', 'label' => __('Offene Aufträge'), 'color' => '#f59e0b'],
            ['key' => 'customers', 'label' => __('Kunden'), 'color' => '#0d9488'],
            ['key' => 'sites', 'label' => __('Standorte'), 'color' => '#9333ea'],
            ['key' => 'drivers', 'label' => __('Fahrer (Zuhause)'), 'color' => '#475569'],
        ];

        return view('tours.map', [
            'from' => $from,
            'to' => $to,
            'targetUser' => $target,
            'selectableUsers' => $auth->isAdmin() ? $this->loadSelectableUsers() : null,
            'statuses' => TourStatus::options(),
            'selectedStatus' => $request->query('status'),
            'markers' => $markers,
            'routes' => $routes,
            'layers' => $layers,
            'tourCount' => $tours->count(),
        ]);
    }

    private function resolveTargetUser(Request $request, User $authUser): ?User {
        return $this->resolveRequestedUserOrAll(
            $request,
            $authUser,
            'Nur Admins dürfen alle Touren einsehen.',
            'Nur Admins dürfen fremde Touren einsehen.',
        );
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
