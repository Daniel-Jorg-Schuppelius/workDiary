<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\Travel\TripKind;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\{TravelLog, User, Vehicle};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Steuerliches Fahrtenbuch (Feature 137, MVP-702): je Fahrzeug + Zeitraum
 * alle wirksamen Fahrten mit km-Ständen, Fahrtart, Ziel, Zweck und Fahrer;
 * Summen je Fahrtart und privater Anteil. Stornierte Originale erscheinen
 * durchgestrichen (lückenlose Nachvollziehbarkeit), zählen aber nicht.
 */
class LogbookReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize('viewAny', Vehicle::class);

        /** @var User $user */
        $user = Auth::user();
        [$fromDate, $toDate] = $this->resolveRange($request);

        $vehicles = Vehicle::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->forUser((int) $user->id))
            ->orderByDesc('logbook_mode')
            ->orderBy('label')
            ->orderBy('license_plate')
            ->get();

        $vehicle = null;
        if ($request->filled('vehicle')) {
            $vehicleId = Sqid::decode(Vehicle::class, $request->string('vehicle')->toString());
            $vehicle = $vehicleId !== null ? $vehicles->firstWhere('id', $vehicleId) : null;
            if ($vehicle instanceof Vehicle) {
                Gate::authorize('view', $vehicle);
            }
        }

        $rows = $vehicle instanceof Vehicle ? $this->rows($vehicle, $fromDate, $toDate) : [];
        $totals = $this->totals($rows);
        $exportFilters = ['vehicle' => $vehicle?->license_plate, 'from' => $fromDate->toDateString(), 'to' => $toDate->toDateString()];

        if ($vehicle instanceof Vehicle && in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($vehicle, $rows, $totals, $fromDate, $toDate, $request, $exportFilters);
        }
        if ($vehicle instanceof Vehicle && $request->query('export') === 'pdf') {
            return $this->pdfDownload('reports.pdf.logbook', [
                'vehicle' => $vehicle,
                'rows' => $rows,
                'totals' => $totals,
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ], sprintf('fahrtenbuch_%s_%s_%s.pdf', preg_replace('/[^A-Za-z0-9]+/', '-', $vehicle->license_plate), $fromDate->toDateString(), $toDate->toDateString()), 'landscape', $request, 'logbook', $exportFilters);
        }

        return view('reports.logbook', [
            'vehicles' => $vehicles,
            'vehicle' => $vehicle,
            'rows' => $rows,
            'totals' => $totals,
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
        ]);
    }

    /**
     * @return list<array{log: TravelLog, km: int|null, superseded: bool}>
     */
    private function rows(Vehicle $vehicle, CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var Collection<int, TravelLog> $logs */
        $logs = TravelLog::query()
            ->with(['user:id,name', 'corrections:id,corrects_travel_log_id', 'corrects:id,date'])
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->orderBy('date')
            ->orderBy('odometer_start_km')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($logs as $log) {
            $superseded = $log->corrections->isNotEmpty();
            $km = $log->odometerDistance();
            if ($km === null) {
                $km = (int) round((float) $log->distance_km * ($log->round_trip ? 2 : 1));
            }
            $rows[] = ['log' => $log, 'km' => $km, 'superseded' => $superseded];
        }

        return $rows;
    }

    /**
     * @param  list<array{log: TravelLog, km: int|null, superseded: bool}>  $rows
     * @return array{km: int, by_kind: array<string, int>, trips: int, private_share: float|null, locked: int}
     */
    private function totals(array $rows): array {
        $byKind = array_fill_keys(TripKind::values(), 0);
        $km = 0;
        $trips = 0;
        $locked = 0;
        foreach ($rows as $row) {
            if ($row['superseded']) {
                continue;
            }
            $trips++;
            $km += (int) $row['km'];
            $byKind[$row['log']->trip_kind->value] += (int) $row['km'];
            if ($row['log']->isLocked()) {
                $locked++;
            }
        }

        return [
            'km' => $km,
            'by_kind' => $byKind,
            'trips' => $trips,
            'private_share' => $km > 0 ? round($byKind[TripKind::Private_->value] / $km * 100, 1) : null,
            'locked' => $locked,
        ];
    }

    /**
     * @param  list<array{log: TravelLog, km: int|null, superseded: bool}>  $rows
     * @param  array{km: int, by_kind: array<string, int>, trips: int, private_share: float|null, locked: int}  $totals
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv(Vehicle $vehicle, array $rows, array $totals, CarbonImmutable $from, CarbonImmutable $to, Request $request, array $exportFilters): SymfonyResponse {
        $filename = sprintf('fahrtenbuch_%s_%s_%s.csv', preg_replace('/[^A-Za-z0-9]+/', '-', $vehicle->license_plate), $from->toDateString(), $to->toDateString());
        $out = [['Datum', 'Start-km', 'End-km', 'km', 'Fahrtart', 'Von', 'Ziel', 'Zweck', 'Fahrer', 'Festgeschrieben', 'Storniert', 'Korrektur zu', 'Korrekturgrund']];
        foreach ($rows as $r) {
            $log = $r['log'];
            $out[] = [
                $log->date?->toDateString() ?? '',
                $log->odometer_start_km !== null ? (string) $log->odometer_start_km : '',
                $log->odometer_end_km !== null ? (string) $log->odometer_end_km : '',
                (string) $r['km'],
                $log->trip_kind->label(),
                (string) $log->from_address,
                (string) $log->to_address,
                (string) $log->purpose,
                $log->user->name ?? '',
                $log->locked_at?->format('Y-m-d H:i') ?? '',
                $r['superseded'] ? 'ja' : '',
                $log->corrects?->date?->toDateString() ?? '',
                (string) $log->correction_reason,
            ];
        }
        foreach (TripKind::cases() as $kind) {
            $out[] = ['Summe ' . $kind->label(), '', '', (string) $totals['by_kind'][$kind->value], $kind->label(), '', '', '', '', '', '', '', ''];
        }
        $out[] = ['Gesamt', '', '', (string) $totals['km'], '', '', '', '', '', '', '', '', ''];
        $out[] = ['Privater Anteil %', '', '', $totals['private_share'] !== null ? NumberHelper::toUSFormat($totals['private_share'], 1) : '', '', '', '', '', '', '', '', '', ''];

        return $this->csvWithMetadata($out, $filename, 'logbook', $exportFilters, $request);
    }
}
