<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DrivingTimeEvidenceExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Organization, TravelLog, User, Vehicle};
use App\Services\Compliance\{ComplianceScanService, DrivingTimeComplianceChecker, DrivingTimeRules};
use App\Support\{Formats, Tz};
use App\Support\Toolkit\CsvFacade;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Lenk-/Ruhezeit-Nachweis (Feature 144, MVP-719) analog zum MiLoG-Export:
 * je Fahrer und Kalendertag erste Abfahrt, letzte Ankunft, Lenkzeit, längste
 * Lenkphase ohne gültige Unterbrechung, Unterbrechungen, Ruhezeit zum
 * Vortag und die Befunde des Tages — als CSV oder PDF (`?format=pdf`).
 * Datenbasis: wirksame Fahrten mit Fahrzeugen, die das Flag
 * `subject_to_driving_time_rules` tragen; ohne Org-Schalter 404.
 *
 * Recht: compliance.viewAny (gleiche Datensicht wie das Compliance-Cockpit).
 */
class DrivingTimeEvidenceExportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __invoke(Request $request, ComplianceScanService $scanner): SymfonyResponse {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $org = $this->currentOrganizationOrNull();
        abort_unless($org instanceof Organization && $org->drivingTimeRulesEnabled(), 404);

        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['user'], $from, $to);

        // Mandantengrenze: User trägt keinen globalen OrganizationScope.
        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $users */
        $users = User::query()
            ->where('organization_id', $org->getKey())
            ->when($filters->userId !== null, fn($q) => $q->whereKey($filters->userId))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'personnel_number']);

        /** @var array<int, string> $vehicleNames */
        $vehicleNames = Vehicle::query()
            ->where('organization_id', $org->getKey())
            ->subjectToDrivingTimeRules()
            ->get(['id', 'label', 'license_plate'])
            ->mapWithKeys(static fn(Vehicle $v): array => [(int) $v->id => $v->displayName()])
            ->all();

        $tz = Tz::current();
        $loadFrom = $from->subDays(DrivingTimeComplianceChecker::LOOKBACK_DAYS);

        /** @var array<int, list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable, vehicle_id: int}>> $tripsByUser */
        $tripsByUser = [];
        if ($vehicleNames !== []) {
            TravelLog::query()
                ->where('organization_id', $org->getKey())
                ->whereIn('user_id', $users->pluck('id')->all())
                ->whereIn('vehicle_id', array_keys($vehicleNames))
                ->whereNotNull('started_at')
                ->whereNotNull('ended_at')
                ->whereBetween('date', [$loadFrom->toDateString(), $to->toDateString()])
                ->effective()
                ->orderBy('started_at')
                ->get(['id', 'user_id', 'vehicle_id', 'started_at', 'ended_at'])
                ->each(function (TravelLog $t) use (&$tripsByUser, $tz): void {
                    if (! $t->started_at || ! $t->ended_at) {
                        return;
                    }
                    $tripsByUser[(int) $t->user_id][] = [
                        'started_at' => CarbonImmutable::parse($t->started_at->toIso8601String())->setTimezone($tz),
                        'ended_at' => CarbonImmutable::parse($t->ended_at->toIso8601String())->setTimezone($tz),
                        'vehicle_id' => (int) $t->vehicle_id,
                    ];
                });
        }

        // Befunde je Fahrer/Tag (dieselbe Ermittlung wie Cockpit und Scan).
        /** @var array<int, array<string, list<string>>> $findingsByUserDate */
        $findingsByUserDate = [];
        foreach ($scanner->drivingTimeFindingsForRange($org, $from, $to) as $uid => $findings) {
            foreach ($findings as $f) {
                $findingsByUserDate[$uid][$f->date][] = (string) __('compliance.report.kind.' . $f->kind);
            }
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        /** @var list<array{driver: string, personnel_number: string, date: string, vehicles: string, start: string, end: string, driving: int, longest_stint: int, breaks: int, rest_before: ?int, findings: string}> $rows */
        $rows = [];
        foreach ($users as $user) {
            $uid = (int) $user->id;
            $trips = DrivingTimeComplianceChecker::normalize($tripsByUser[$uid] ?? []);
            if ($trips === []) {
                continue;
            }
            $days = DrivingTimeComplianceChecker::aggregateDays($trips);
            $previousEnd = null;
            foreach ($days as $date => $agg) {
                $dayTrips = array_values(array_filter($trips, static fn(array $t): bool => $t['started_at']->toDateString() === $date));
                $restBefore = null;
                if ($previousEnd !== null && $agg['first_start']->greaterThan($previousEnd)) {
                    $gap = (int) $previousEnd->diffInMinutes($agg['first_start'], false);
                    $restBefore = $gap < DrivingTimeRules::DAILY_WINDOW_MINUTES ? $gap : null;
                }
                $previousEnd = $agg['last_end'];
                if ($date < $fromStr || $date > $toStr) {
                    continue; // Vorlauf dient nur Ruhezeit/Befunden.
                }

                $vehicles = [];
                $breaks = 0;
                for ($i = 0, $n = count($dayTrips); $i < $n; $i++) {
                    $vehicles[$dayTrips[$i]['vehicle_id']] = $vehicleNames[$dayTrips[$i]['vehicle_id']] ?? '#' . $dayTrips[$i]['vehicle_id'];
                    if ($i > 0) {
                        $gap = (int) $dayTrips[$i - 1]['ended_at']->diffInMinutes($dayTrips[$i]['started_at'], false);
                        if ($gap >= DrivingTimeRules::BREAK_SPLIT_FIRST_MINUTES) {
                            $breaks += $gap;
                        }
                    }
                }

                $rows[] = [
                    'driver' => (string) $user->name,
                    'personnel_number' => (string) ($user->personnel_number ?? ''),
                    'date' => (string) $date,
                    'vehicles' => implode(', ', $vehicles),
                    'start' => $agg['first_start']->format('H:i'),
                    'end' => $agg['last_end']->format('H:i'),
                    'driving' => $agg['minutes'],
                    'longest_stint' => DrivingTimeRules::evaluateBreaks($dayTrips)['max_accumulated'],
                    'breaks' => $breaks,
                    'rest_before' => $restBefore,
                    'findings' => implode('; ', $findingsByUserDate[$uid][$date] ?? []),
                ];
            }
        }

        $auditFilters = $filters->toAuditArray();
        if ($request->query('format') === 'pdf') {
            return $this->pdfDownload('reports.pdf.driving-time-evidence', [
                'rows' => $rows,
                'from' => $fromStr,
                'to' => $toStr,
            ], sprintf('lenkzeit_nachweis_%s_%s.pdf', $fromStr, $toStr), 'landscape', $request, 'driving_time_evidence', $auditFilters);
        }

        $headers = [
            (string) __('compliance.driving.csv.driver'),
            (string) __('compliance.driving.csv.personnel_number'),
            (string) __('compliance.driving.csv.date'),
            (string) __('compliance.driving.csv.vehicles'),
            (string) __('compliance.driving.csv.start'),
            (string) __('compliance.driving.csv.end'),
            (string) __('compliance.driving.csv.driving'),
            (string) __('compliance.driving.csv.longest_stint'),
            (string) __('compliance.driving.csv.breaks'),
            (string) __('compliance.driving.csv.rest_before'),
            (string) __('compliance.driving.csv.findings'),
        ];
        $csvRows = [];
        foreach ($rows as $r) {
            $csvRows[] = array_combine($headers, [
                $r['driver'],
                $r['personnel_number'],
                $r['date'],
                $r['vehicles'],
                $r['start'],
                $r['end'],
                Formats::duration($r['driving'], 'clock'),
                Formats::duration($r['longest_stint'], 'clock'),
                $r['breaks'],
                $r['rest_before'] === null ? '' : Formats::duration($r['rest_before'], 'clock'),
                $r['findings'],
            ]);
        }

        $this->auditExport($request, 'driving_time_evidence', 'csv', $auditFilters);

        $filename = sprintf('lenkzeit_nachweis_%s_%s.csv', $fromStr, $toStr);

        // BOM für Excel-Kompatibilität; Inhalt über die CsvFacade (Toolkit).
        return response("\u{FEFF}" . CsvFacade::buildCsv($headers, $csvRows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
