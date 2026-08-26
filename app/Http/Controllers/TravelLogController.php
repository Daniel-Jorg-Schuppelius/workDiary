<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelLogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Exceptions\{LogbookViolationException, TravelLogLockedException};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveTravelLogRequest;
use App\Models\{Customer, Project, TravelLog, User, Vehicle};
use App\Services\Travel\{LogbookRules, TravelLogService};
use App\Support\{CsvExport, SortableQuery, Sqid};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelLogController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly TravelLogService $service,
        private readonly LogbookRules $logbook,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', TravelLog::class);

        [$from, $to] = $this->resolveRange($request);

        $vehicle = $request->string('vehicle')->toString();
        $vehicleEnum = TravelLogVehicle::tryFrom($vehicle);
        $vehicleValue = $vehicleEnum?->value;

        $query = TravelLog::query()
            ->with(['vehicleEntity:id,license_plate,label,logbook_mode', 'corrections:id,corrects_travel_log_id'])
            ->where('user_id', Auth::id())
            ->whereBetween('date', DateRange::days($from, $to))
            ->when($vehicleValue, fn ($q) => $q->where('vehicle', $vehicleValue));

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'date' => 'date',
            'from' => 'from_address',
            'to' => 'to_address',
            'distance' => 'distance_km',
            'vehicle' => 'vehicle',
            'reimbursement' => 'reimbursement_total',
            'purpose' => 'purpose',
        ], 'date', 'desc');

        $logs = $query->paginate(25)->withQueryString();

        $totals = [
            'distance_km' => (float) TravelLog::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', DateRange::days($from, $to))
                ->when($vehicleValue, fn ($q) => $q->where('vehicle', $vehicleValue))
                ->sum('distance_km'),
            'reimbursement' => (float) TravelLog::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', DateRange::days($from, $to))
                ->when($vehicleValue, fn ($q) => $q->where('vehicle', $vehicleValue))
                ->sum('reimbursement_total'),
        ];

        return view('travel-logs.index', [
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'sort' => $sort,
            'dir' => $dir,
            'vehicles' => TravelLogVehicle::cases(),
            'selectedVehicle' => $vehicleValue,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', TravelLog::class);

        // Stornofahrt (Feature 137): Vorbelegung aus der festgeschriebenen Original-Fahrt.
        $correcting = null;
        if ($request->filled('corrects')) {
            $correcting = TravelLog::query()->findOrFail(Sqid::decodeOrAbort(TravelLog::class, $request->string('corrects')->toString()));
            Gate::authorize('view', $correcting);
        }

        return view('travel-logs._form_dialog', array_merge($this->formOptions(), [
            'log' => null,
            'correcting' => $correcting,
            'date' => $correcting?->date?->toDateString() ?? $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
        ]));
    }

    public function store(SaveTravelLogRequest $request): RedirectResponse {
        Gate::authorize('create', TravelLog::class);

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        /** @var User $user */
        $user = Auth::user();
        $data['organization_id'] = $user->organization_id;

        $correctsId = $data['corrects_travel_log_id'] ?? null;
        $reason = (string) ($data['correction_reason'] ?? '');
        unset($data['corrects_travel_log_id'], $data['correction_reason']);

        try {
            if ($correctsId !== null) {
                $original = TravelLog::query()->findOrFail((int) $correctsId);
                Gate::authorize('view', $original);
                $log = $this->service->correct($original, $data, $reason, $user);
            } else {
                $log = $this->service->create($data);
            }
        } catch (LogbookViolationException $e) {
            return back()->withInput()->withErrors($e->errors);
        }

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt erfasst (:km km).', ['km' => NumberHelper::toGermanFormat((float) $log->distance_km, 2, withThousandsSeparator: true)]))
            ->with('warning', $this->chainWarning($log));
    }

    public function edit(TravelLog $travelLog): View|RedirectResponse {
        Gate::authorize('update', $travelLog);

        if ($travelLog->isLocked()) {
            return redirect()->route('travel-logs.index')->with('error', (new TravelLogLockedException($travelLog))->getMessage());
        }

        return view('travel-logs._form_dialog', array_merge($this->formOptions(), [
            'log' => $travelLog,
            'correcting' => null,
            'date' => $travelLog->date?->toDateString(),
        ]));
    }

    public function update(SaveTravelLogRequest $request, TravelLog $travelLog): RedirectResponse {
        Gate::authorize('update', $travelLog);

        $data = $request->validated();
        unset($data['corrects_travel_log_id'], $data['correction_reason']);

        try {
            $log = $this->service->update($travelLog, $data);
        } catch (TravelLogLockedException $e) {
            return redirect()->route('travel-logs.index')->with('error', $e->getMessage());
        } catch (LogbookViolationException $e) {
            return back()->withInput()->withErrors($e->errors);
        }

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt aktualisiert.'))
            ->with('warning', $this->chainWarning($log));
    }

    public function destroy(TravelLog $travelLog): RedirectResponse {
        Gate::authorize('delete', $travelLog);

        try {
            $this->service->delete($travelLog);
        } catch (TravelLogLockedException $e) {
            return redirect()->route('travel-logs.index')->with('error', $e->getMessage());
        }

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt gelöscht.'));
    }

    /** Explizite Festschreibung (Feature 137) — nur Fahrtenbuch-Modus. */
    public function lock(TravelLog $travelLog): RedirectResponse {
        Gate::authorize('update', $travelLog);

        if (! $travelLog->isLogbook()) {
            return redirect()->route('travel-logs.index')->with('error', __('Nur Fahrten eines Fahrzeugs im Fahrtenbuch-Modus werden festgeschrieben.'));
        }

        /** @var User $user */
        $user = Auth::user();
        $this->service->lock($travelLog, $user);

        return redirect()->route('travel-logs.index')->with('success', __('Fahrt festgeschrieben.'));
    }

    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', TravelLog::class);

        [$from, $to] = $this->resolveRange($request);

        $filename = sprintf('travel-logs-%s_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $logs = TravelLog::query()
            ->with(['project:id,name,slug,customer_id', 'customer:id,name,slug'])
            ->where('user_id', Auth::id())
            ->whereBetween('date', DateRange::days($from, $to))
            ->orderBy('date')
            ->get();

        // Abweichung zum Alt-Export: jetzt mit UTF-8-BOM (Mehrheits-Semantik der CSV-Exporte).
        $rows = (static function () use ($logs): \Generator {
            foreach ($logs as $log) {
                yield [
                    $log->date?->format('Y-m-d'),
                    (string) $log->from_address,
                    (string) $log->to_address,
                    number_format((float) $log->distance_km, 2, ',', ''),
                    $log->round_trip ? 'ja' : 'nein',
                    $log->vehicle->value,
                    number_format((float) ($log->rate_per_km ?? 0), 4, ',', ''),
                    number_format((float) $log->reimbursement_total, 2, ',', ''),
                    $log->project->name ?? '',
                    $log->customer->name ?? '',
                    (string) $log->purpose,
                    (int) $log->duration_minutes,
                ];
            }
        })();

        return CsvExport::streamFromRows($filename, [
            'Datum',
            'Von',
            'Nach',
            'KM',
            'Hin/Rück',
            'Fahrzeug',
            'Satz €/km',
            'Erstattung €',
            'Projekt',
            'Kunde',
            'Zweck',
            'Dauer min',
        ], $rows);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array {
        /** @var User $user */
        $user = Auth::user();

        return [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => TravelLogVehicle::cases(),
            'fleetVehicles' => Vehicle::query()->active()->forUser((int) $user->id)->orderBy('label')->orderBy('license_plate')->get(),
            'tripKinds' => TripKind::cases(),
            'rates' => (array) config('timesheet.travel.rates', []),
        ];
    }

    /**
     * Erstattungsmodus: Lücke in der km-Kette ist nur eine Warnung (im
     * Fahrtenbuch-Modus blockiert der Service).
     */
    private function chainWarning(TravelLog $log): ?string {
        $vehicle = $log->vehicleEntity;
        if (! $vehicle instanceof Vehicle || $vehicle->logbook_mode || $log->odometer_start_km === null) {
            return null;
        }
        $expected = $this->logbook->lastOdometerEnd($vehicle, $log);
        if ($expected === null || $expected === $log->odometer_start_km) {
            return null;
        }

        return (string) __('Hinweis: Lücke in der km-Kette — letzte Fahrt endete bei :expected km, diese beginnt bei :start km.', [
            'expected' => $expected,
            'start' => $log->odometer_start_km,
        ]);
    }
}
