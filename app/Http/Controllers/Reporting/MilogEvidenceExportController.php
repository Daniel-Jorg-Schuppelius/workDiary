<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MilogEvidenceExportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Attendance\AttendanceStatus;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Attendance, Organization, User};
use App\Support\Query\DateRange;
use App\Support\Toolkit\CsvFacade;
use App\Support\Tz;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;

/**
 * Zoll-Nachweisexport nach §17 Abs. 1 MiLoG / §2a SchwarzArbG (Feature 131,
 * MVP-695): Beginn, Ende und Dauer der täglichen Arbeitszeit je Arbeitnehmer
 * als CSV — Quelle sind die Stempelzeiten (attendances), je Kalendertag
 * aggregiert (Beginn = erste Start-, Ende = letzte Endzeit, Pausen summiert,
 * Dauer = netto). Deterministisch sortiert (Mitarbeitername, ID, Datum).
 *
 * Recht: bestehendes compliance.viewAny (gleiche Datensicht wie der
 * ArbZG-Report) — bewusst KEIN neues globales Recht.
 */
class MilogEvidenceExportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __invoke(Request $request): Response {
        Gate::authorize(Permission::ComplianceViewAny->value);

        $org = $this->currentOrganizationOrNull();
        abort_unless($org instanceof Organization, 404);

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

        // Stempel-Spannen je User/Kalendertag aggregieren (ohne abgesagte/offene).
        /** @var array<int, array<string, array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon, gross: int, breaks: int}>> $byUserDate */
        $byUserDate = [];
        Attendance::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->whereBetween('date', DateRange::days($from, $to))
            ->whereNotIn('status', [AttendanceStatus::Cancelled->value, AttendanceStatus::Open->value])
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->orderBy('started_at')
            ->get()
            ->each(function (Attendance $a) use (&$byUserDate): void {
                if (! $a->started_at || ! $a->ended_at || ! $a->date) {
                    return;
                }
                $dateKey = $a->date->toDateString();
                $slot = $byUserDate[(int) $a->user_id][$dateKey] ?? null;
                $gross = max(0, (int) $a->started_at->diffInMinutes($a->ended_at, false));
                if ($slot === null) {
                    $slot = ['start' => $a->started_at, 'end' => $a->ended_at, 'gross' => 0, 'breaks' => 0];
                }
                $slot['gross'] += $gross;
                $slot['breaks'] += $a->break_minutes_total;
                if ($a->started_at->lessThan($slot['start'])) {
                    $slot['start'] = $a->started_at;
                }
                if ($a->ended_at->greaterThan($slot['end'])) {
                    $slot['end'] = $a->ended_at;
                }
                $byUserDate[(int) $a->user_id][$dateKey] = $slot;
            });

        $headers = [
            (string) __('compliance.milog.csv.employee'),
            (string) __('compliance.milog.csv.personnel_number'),
            (string) __('compliance.milog.csv.date'),
            (string) __('compliance.milog.csv.start'),
            (string) __('compliance.milog.csv.end'),
            (string) __('compliance.milog.csv.breaks'),
            (string) __('compliance.milog.csv.duration'),
        ];

        $tz = Tz::current();
        $rows = [];
        foreach ($users as $user) {
            $days = $byUserDate[(int) $user->id] ?? [];
            ksort($days);
            foreach ($days as $date => $agg) {
                $net = max(0, $agg['gross'] - $agg['breaks']);
                $rows[] = array_combine($headers, [
                    (string) $user->name,
                    (string) ($user->personnel_number ?? ''),
                    $date,
                    $agg['start']->copy()->setTimezone($tz)->format('H:i'),
                    $agg['end']->copy()->setTimezone($tz)->format('H:i'),
                    $agg['breaks'],
                    sprintf('%d:%02d', intdiv($net, 60), $net % 60),
                ]);
            }
        }

        $this->auditExport($request, 'milog_evidence', 'csv', $filters->toAuditArray());

        $filename = sprintf('milog_nachweis_%s_%s.csv', $from->toDateString(), $to->toDateString());

        // BOM für Excel-Kompatibilität; Inhalt über die CsvFacade (Toolkit).
        return response("\u{FEFF}" . CsvFacade::buildCsv($headers, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
