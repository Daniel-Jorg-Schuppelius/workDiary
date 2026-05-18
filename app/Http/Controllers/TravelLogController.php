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

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveTravelLogRequest;
use App\Models\Customer;
use App\Models\Project;
use App\Models\TravelLog;
use App\Models\User;
use App\Services\Travel\TravelLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelLogController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly TravelLogService $service,
    ) {
    }

    public function index(Request $request): View {
        Gate::authorize('viewAny', TravelLog::class);

        [$from, $to] = $this->resolveRange($request);

        $logs = TravelLog::query()
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $totals = [
            'distance_km' => (float) TravelLog::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('distance_km'),
            'reimbursement' => (float) TravelLog::query()
                ->where('user_id', Auth::id())
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->sum('reimbursement_total'),
        ];

        return view('travel-logs.index', [
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', TravelLog::class);

        return view('travel-logs._form_dialog', [
            'log' => null,
            'date' => $request->date('date')?->toDateString() ?? CarbonImmutable::today()->toDateString(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => TravelLog::VEHICLES,
            'rates' => (array) config('timesheet.travel.rates', []),
        ]);
    }

    public function store(SaveTravelLogRequest $request): RedirectResponse {
        Gate::authorize('create', TravelLog::class);

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        /** @var User $user */
        $user = Auth::user();
        $data['organization_id'] = $user->organization_id;

        $log = $this->service->create($data);

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt erfasst (:km km).', ['km' => number_format((float) $log->distance_km, 2, ',', '.')]));
    }

    public function edit(TravelLog $travelLog): View {
        Gate::authorize('update', $travelLog);

        return view('travel-logs._form_dialog', [
            'log' => $travelLog,
            'date' => $travelLog->date?->toDateString(),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => TravelLog::VEHICLES,
            'rates' => (array) config('timesheet.travel.rates', []),
        ]);
    }

    public function update(SaveTravelLogRequest $request, TravelLog $travelLog): RedirectResponse {
        Gate::authorize('update', $travelLog);

        $this->service->update($travelLog, $request->validated());

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt aktualisiert.'));
    }

    public function destroy(TravelLog $travelLog): RedirectResponse {
        Gate::authorize('delete', $travelLog);

        $this->service->delete($travelLog);

        return redirect()->route('travel-logs.index')
            ->with('success', __('Fahrt gelöscht.'));
    }

    public function export(Request $request): StreamedResponse {
        Gate::authorize('viewAny', TravelLog::class);

        [$from, $to] = $this->resolveRange($request);

        $filename = sprintf('travel-logs-%s_%s.csv', $from->format('Y-m-d'), $to->format('Y-m-d'));

        $logs = TravelLog::query()
            ->with(['project:id,name', 'customer:id,name'])
            ->where('user_id', Auth::id())
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
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
            ], ';');
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->date?->format('Y-m-d'),
                    (string) $log->from_address,
                    (string) $log->to_address,
                    number_format((float) $log->distance_km, 2, ',', ''),
                    $log->round_trip ? 'ja' : 'nein',
                    (string) $log->vehicle,
                    number_format((float) ($log->rate_per_km ?? 0), 4, ',', ''),
                    number_format((float) $log->reimbursement_total, 2, ',', ''),
                    $log->project->name ?? '',
                    $log->customer->name ?? '',
                    (string) $log->purpose,
                    (int) $log->duration_minutes,
                ], ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Vorrang: explizites ?from/?to im Request → globaler Header-Range.
     *
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
}
