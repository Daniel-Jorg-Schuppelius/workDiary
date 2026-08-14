<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FleetReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{EnergyLog, TravelLog, Vehicle};
use App\Services\Reporting\ReportFilters;
use App\Support\ChartBucket;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Fuhrpark-Auswertung: Kilometer, Verbrauch, Tank-/Ladekosten und €/km
 * pro Fahrzeug im gewählten Zeitraum.
 */
class FleetReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$fromDate, $toDate] = $this->resolveRange($request);
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $filters = $this->standardFilters($request, ['user'], $fromDate, $toDate, scope: $scope);

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId, $filters);
        $totals = $this->totals($rows);
        $vehicleKmSeries = $this->vehicleKmSeries($rows);
        $exportFilters = array_merge(['scope' => $scope], $filters->toAuditArray());

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($rows, $totals, $from, $to, $request, $exportFilters);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $vehicleKmSeries, $request, $exportFilters);
        }

        return view('reports.fleet', [
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'rows' => $rows,
            'totals' => $totals,
            'standardFilters' => $filters,
            'filterFields' => ['user'],
            'vehicleKmSeries' => $vehicleKmSeries,
            'monthlyKmSeries' => $this->monthlyKmSeries($fromDate, $toDate, $scope, $userId, $filters),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($fromDate, $toDate)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($fromDate, $toDate)),
            ...$this->standardFilterOptions(['user'], $filters),
        ]);
    }
    /**
     * @return array<int, array{
     *   vehicle: Vehicle,
     *   trip_count: int,
     *   km: float,
     *   reimbursement: float,
     *   fuel_count: int,
     *   liters: float,
     *   kwh: float,
     *   energy_cost: float,
     *   cost_per_km: float|null,
     *   last_odometer: int|null
     * }>
     */
    private function aggregate(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $travelQuery = TravelLog::query()
            ->whereNotNull('vehicle_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('vehicle_id', 'distance_km', 'reimbursement_total', 'user_id');
        $energyQuery = EnergyLog::query()
            ->whereBetween('started_at', [$from, $to])
            ->select('vehicle_id', 'unit', 'quantity', 'cost_total', 'odometer_km', 'user_id');
        if ($scope === 'mine') {
            $travelQuery->where('user_id', $userId);
            $energyQuery->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($travelQuery);
        $filters->applyUserAndTeam($energyQuery);

        /** @var array<int, array{trip_count:int, km:float, reimbursement:float}> $travelByVehicle */
        $travelByVehicle = [];
        foreach ($travelQuery->get() as $t) {
            $vid = (int) $t->vehicle_id;
            if (! isset($travelByVehicle[$vid])) {
                $travelByVehicle[$vid] = ['trip_count' => 0, 'km' => 0.0, 'reimbursement' => 0.0];
            }
            $travelByVehicle[$vid]['trip_count']++;
            $travelByVehicle[$vid]['km'] += (float) $t->distance_km;
            $travelByVehicle[$vid]['reimbursement'] += (float) $t->reimbursement_total;
        }

        /** @var array<int, array{fuel_count:int, liters:float, kwh:float, energy_cost:float, last_odometer:int|null}> $energyByVehicle */
        $energyByVehicle = [];
        foreach ($energyQuery->orderBy('started_at')->get() as $e) {
            $vid = (int) $e->vehicle_id;
            if (! isset($energyByVehicle[$vid])) {
                $energyByVehicle[$vid] = [
                    'fuel_count' => 0,
                    'liters' => 0.0,
                    'kwh' => 0.0,
                    'energy_cost' => 0.0,
                    'last_odometer' => null,
                ];
            }
            $energyByVehicle[$vid]['fuel_count']++;
            if ($e->unit === EnergyLog::UNIT_LITER) {
                $energyByVehicle[$vid]['liters'] += (float) $e->quantity;
            } elseif ($e->unit === EnergyLog::UNIT_KWH) {
                $energyByVehicle[$vid]['kwh'] += (float) $e->quantity;
            }
            $energyByVehicle[$vid]['energy_cost'] += (float) ($e->cost_total ?? 0);
            if ($e->odometer_km !== null) {
                $energyByVehicle[$vid]['last_odometer'] = (int) $e->odometer_km;
            }
        }

        $vehicleIds = array_unique(array_merge(array_keys($travelByVehicle), array_keys($energyByVehicle)));
        if ($vehicleIds === []) {
            return [];
        }
        /** @var Collection<int, Vehicle> $vehicles */
        $vehicles = Vehicle::query()->whereIn('id', $vehicleIds)->orderBy('license_plate')->get();

        $rows = [];
        foreach ($vehicles as $vehicle) {
            $vid = (int) $vehicle->id;
            $travel = $travelByVehicle[$vid] ?? ['trip_count' => 0, 'km' => 0.0, 'reimbursement' => 0.0];
            $energy = $energyByVehicle[$vid] ?? ['fuel_count' => 0, 'liters' => 0.0, 'kwh' => 0.0, 'energy_cost' => 0.0, 'last_odometer' => null];
            $cpk = $travel['km'] > 0 && $energy['energy_cost'] > 0
                ? $energy['energy_cost'] / $travel['km']
                : null;
            $rows[] = [
                'vehicle' => $vehicle,
                'trip_count' => (int) $travel['trip_count'],
                'km' => (float) $travel['km'],
                'reimbursement' => (float) $travel['reimbursement'],
                'fuel_count' => (int) $energy['fuel_count'],
                'liters' => (float) $energy['liters'],
                'kwh' => (float) $energy['kwh'],
                'energy_cost' => (float) $energy['energy_cost'],
                'cost_per_km' => $cpk,
                'last_odometer' => $energy['last_odometer'] ?? $vehicle->odometer_km,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @return array{km:float, trip_count:int, fuel_count:int, liters:float, kwh:float, energy_cost:float, reimbursement:float, vehicles:int, avg_cost_per_km:float|null}
     */
    private function totals(array $rows): array {
        $km = 0.0;
        $tripCount = 0;
        $fuelCount = 0;
        $liters = 0.0;
        $kwh = 0.0;
        $energyCost = 0.0;
        $reimbursement = 0.0;
        foreach ($rows as $row) {
            $km += $row['km'];
            $tripCount += $row['trip_count'];
            $fuelCount += $row['fuel_count'];
            $liters += $row['liters'];
            $kwh += $row['kwh'];
            $energyCost += $row['energy_cost'];
            $reimbursement += $row['reimbursement'];
        }

        return [
            'km' => $km,
            'trip_count' => $tripCount,
            'fuel_count' => $fuelCount,
            'liters' => $liters,
            'kwh' => $kwh,
            'energy_cost' => $energyCost,
            'reimbursement' => $reimbursement,
            'vehicles' => count($rows),
            'avg_cost_per_km' => $km > 0 && $energyCost > 0 ? $energyCost / $km : null,
        ];
    }

    /**
     * Kilometer je Fahrzeug (Top 15) — nur Fahrzeuge mit Fahrleistung
     * (Chart-Datenkontrakt Screen + PDF).
     *
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @return list<array{x: string, y: float}>
     */
    private function vehicleKmSeries(array $rows): array {
        return array_values(collect($rows)
            ->filter(static fn(array $r): bool => $r['km'] > 0)
            ->sortByDesc('km')
            ->take(15)
            ->map(static fn(array $r): array => [
                'x' => (string) $r['vehicle']->license_plate . ($r['vehicle']->label !== null && $r['vehicle']->label !== '' ? ' — ' . $r['vehicle']->label : ''),
                'y' => round((float) $r['km'], 1),
            ])
            ->all());
    }

    /**
     * Kilometer je Bucket (adaptiv zur Header-Granularität; leere Serie statt
     * Null-Achse, §Diagramm-UX).
     *
     * @return list<array{x: string, y: float}>
     */
    private function monthlyKmSeries(CarbonImmutable $from, CarbonImmutable $to, string $scope, int $userId, ReportFilters $filters): array {
        $granularity = $this->bucketGranularity($from, $to);
        $q = TravelLog::query()
            ->whereNotNull('vehicle_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->select('date', 'distance_km');
        if ($scope === 'mine') {
            $q->where('user_id', $userId);
        }
        $filters->applyUserAndTeam($q);

        /** @var array<string, float> $byKey */
        $byKey = [];
        foreach ($q->get() as $t) {
            $key = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $t->date))[0];
            $byKey[$key] = ($byKey[$key] ?? 0.0) + (float) $t->distance_km;
        }
        if ($byKey === [] || array_sum($byKey) <= 0) {
            return [];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $series[] = ['x' => $bucket['shortLabel'], 'y' => round($byKey[$bucket['key']] ?? 0.0, 1)];
        }

        return $series;
    }

    /**
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @param  array{km:float, trip_count:int, fuel_count:int, liters:float, kwh:float, energy_cost:float, reimbursement:float, vehicles:int, avg_cost_per_km:float|null}  $totals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to, Request $request, array $exportFilters): Response {
        $filename = sprintf('fuhrpark_%s_%s.csv', $from, $to);
        $out = [['Kennzeichen', 'Bezeichnung', 'Antrieb', 'Fahrten', 'km', 'Erstattung', 'Tankungen', 'Liter', 'kWh', 'Energiekosten', '€/km', 'Tachostand']];
        foreach ($rows as $r) {
            $v = $r['vehicle'];
            $out[] = [
                (string) $v->license_plate,
                (string) ($v->label ?? ''),
                $v->propulsion->value,
                $r['trip_count'],
                NumberHelper::toUSFormat($r['km'], 2),
                NumberHelper::toUSFormat($r['reimbursement'], 2),
                $r['fuel_count'],
                NumberHelper::toUSFormat($r['liters'], 2),
                NumberHelper::toUSFormat($r['kwh'], 2),
                NumberHelper::toUSFormat($r['energy_cost'], 2),
                $r['cost_per_km'] !== null ? NumberHelper::toUSFormat($r['cost_per_km'], 3) : '',
                $r['last_odometer'] !== null ? (string) $r['last_odometer'] : '',
            ];
        }
        $out[] = [
            'Gesamt',
            '',
            '',
            $totals['trip_count'],
            NumberHelper::toUSFormat($totals['km'], 2),
            NumberHelper::toUSFormat($totals['reimbursement'], 2),
            $totals['fuel_count'],
            NumberHelper::toUSFormat($totals['liters'], 2),
            NumberHelper::toUSFormat($totals['kwh'], 2),
            NumberHelper::toUSFormat($totals['energy_cost'], 2),
            $totals['avg_cost_per_km'] !== null ? NumberHelper::toUSFormat($totals['avg_cost_per_km'], 3) : '',
            '',
        ];

        return $this->csvWithMetadata($out, $filename, 'fleet', $exportFilters, $request);
    }

    /**
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @param  array{km:float, trip_count:int, fuel_count:int, liters:float, kwh:float, energy_cost:float, reimbursement:float, vehicles:int, avg_cost_per_km:float|null}  $totals
     * @param  list<array{x: string, y: float}>  $vehicleKmSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, array $vehicleKmSeries, Request $request, array $exportFilters): SymfonyResponse {
        $filename = sprintf('fuhrpark_%s_%s.pdf', $from, $to);
        return $this->pdfDownload('reports.pdf.fleet', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => (string) ($exportFilters['scope'] ?? 'mine'),
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Kilometer je Fahrzeug (Top 15)'),
                'unit' => 'km',
                'xLabel' => __('Fahrzeug'),
                'yLabel' => __('km'),
                'series' => $vehicleKmSeries,
            ],
        ], $filename, 'landscape', $request, 'fleet', $exportFilters);
    }
}
