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
use App\Models\EnergyLog;
use App\Models\TravelLog;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Fuhrpark-Auswertung: Kilometer, Verbrauch, Tank-/Ladekosten und €/km
 * pro Fahrzeug im gewählten Zeitraum.
 */
class FleetReportController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $authUser = Auth::user();
        $isAdmin = $authUser instanceof User && $authUser->isAdmin();
        $scope = $this->resolveScope($request, $isAdmin);

        $range = $this->globalDateRange();
        $fromDate = Carbon::parse($range['from']->toDateString())->startOfDay();
        $toDate = Carbon::parse($range['to']->toDateString())->endOfDay();
        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $rows = $this->aggregate($fromDate, $toDate, $scope, $userId);
        $totals = $this->totals($rows);

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($rows, $totals, $from, $to);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($rows, $totals, $from, $to, $scope);
        }

        return view('reports.fleet', [
            'from' => $from,
            'to' => $to,
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
    private function aggregate(Carbon $from, Carbon $to, string $scope, int $userId): array {
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
        /** @var \Illuminate\Database\Eloquent\Collection<int, Vehicle> $vehicles */
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
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @param  array{km:float, trip_count:int, fuel_count:int, liters:float, kwh:float, energy_cost:float, reimbursement:float, vehicles:int, avg_cost_per_km:float|null}  $totals
     */
    private function exportCsv(array $rows, array $totals, string $from, string $to): Response {
        $filename = sprintf('fuhrpark_%s_%s.csv', $from, $to);
        $out = [['Kennzeichen', 'Bezeichnung', 'Antrieb', 'Fahrten', 'km', 'Erstattung', 'Tankungen', 'Liter', 'kWh', 'Energiekosten', '€/km', 'Tachostand']];
        foreach ($rows as $r) {
            $v = $r['vehicle'];
            $out[] = [
                (string) $v->license_plate,
                (string) ($v->label ?? ''),
                (string) $v->propulsion,
                $r['trip_count'],
                number_format($r['km'], 2, '.', ''),
                number_format($r['reimbursement'], 2, '.', ''),
                $r['fuel_count'],
                number_format($r['liters'], 2, '.', ''),
                number_format($r['kwh'], 2, '.', ''),
                number_format($r['energy_cost'], 2, '.', ''),
                $r['cost_per_km'] !== null ? number_format($r['cost_per_km'], 3, '.', '') : '',
                $r['last_odometer'] !== null ? (string) $r['last_odometer'] : '',
            ];
        }
        $out[] = [
            'Gesamt',
            '',
            '',
            $totals['trip_count'],
            number_format($totals['km'], 2, '.', ''),
            number_format($totals['reimbursement'], 2, '.', ''),
            $totals['fuel_count'],
            number_format($totals['liters'], 2, '.', ''),
            number_format($totals['kwh'], 2, '.', ''),
            number_format($totals['energy_cost'], 2, '.', ''),
            $totals['avg_cost_per_km'] !== null ? number_format($totals['avg_cost_per_km'], 3, '.', '') : '',
            '',
        ];

        $csv = '';
        foreach ($out as $row) {
            $csv .= implode(';', array_map(static function ($v): string {
                $s = (string) $v;
                if (str_contains($s, ';') || str_contains($s, '"') || str_contains($s, "\n")) {
                    $s = '"' . str_replace('"', '""', $s) . '"';
                }

                return $s;
            }, $row)) . "\r\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * @param  array<int, array{vehicle: Vehicle, trip_count:int, km:float, reimbursement:float, fuel_count:int, liters:float, kwh:float, energy_cost:float, cost_per_km:float|null, last_odometer:int|null}>  $rows
     * @param  array{km:float, trip_count:int, fuel_count:int, liters:float, kwh:float, energy_cost:float, reimbursement:float, vehicles:int, avg_cost_per_km:float|null}  $totals
     */
    private function exportPdf(array $rows, array $totals, string $from, string $to, string $scope): SymfonyResponse {
        $filename = sprintf('fuhrpark_%s_%s.pdf', $from, $to);
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('reports.pdf.fleet', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from,
            'to' => $to,
            'scope' => $scope,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
