<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CohortComparisonReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Qualification, User};
use App\Services\Reporting\CohortComparisonBuilder;
use App\Support\Sqid;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Feature 002 (Kohortenvergleich vor/nach Fortbildung): vergleicht eine
 * Kennzahl je Mitarbeitendem im Zeitraum vor und nach dem Erwerb einer
 * gewählten Qualifikation/Fortbildung. Org-weite Personaldaten → nur
 * report.view-Berechtigte bzw. Admin.
 */
class CohortComparisonReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    public function __construct(private readonly CohortComparisonBuilder $builder) {}

    public function index(Request $request): View|Response|SymfonyResponse {
        $authUser = Auth::user();
        $allowed = $authUser instanceof User
            && ($authUser->isAdmin() || $authUser->can(Permission::ReportView->value));
        abort_unless($allowed, 403);

        $qualifications = Qualification::query()->orderBy('name')->get(['id', 'name']);

        $metricOptions = CohortComparisonBuilder::metricOptions();
        $metric = (string) $request->query('metric', 'billableRate');
        if (! array_key_exists($metric, $metricOptions)) {
            $metric = 'billableRate';
        }

        $window = (int) $request->query('window', 90);
        $window = max(7, min(365, $window));

        // Zeitraum wirkt hier nicht auf die Kennzahl (Fenster hängt am
        // Erwerbsdatum) — er speist nur das Standard-Filterset (Team).
        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['team'], $from, $to);

        $rawQualId = $request->query('qualification_id');
        $qualId = Sqid::decodeOrNumeric(Qualification::class, $rawQualId);

        $result = null;
        $qualification = null;
        if ($qualId !== null) {
            $qualification = Qualification::query()->find($qualId);
            if ($qualification !== null) {
                $result = $this->builder->build($qualification, $metric, $window, $filters->teamUserIds());

                if ($request->query('export') === 'csv') {
                    return $this->exportCsv($result, (string) $qualification->name, $metricOptions[$metric], $filters->toAuditArray(), $request);
                }
            }
        }

        return view('reports.cohort-comparison', [
            'qualifications' => $qualifications,
            'qualificationId' => $qualId,
            'qualification' => $qualification,
            'metric' => $metric,
            'metricOptions' => $metricOptions,
            'window' => $window,
            'result' => $result,
            'standardFilters' => $filters,
            'filterFields' => ['team'],
            'beforeAfterSeries' => $result === null ? [] : $this->beforeAfterSeries($result['members']),
            'weeklySeries' => $result === null ? [] : $this->weeklySeries($result['weekly']),
            ...$this->standardFilterOptions(['team'], $filters),
        ]);
    }

    /**
     * Vorher- vs. Nachher-Wert der Kennzahl je Kohortenmitglied (y2 =
     * nachher, Schraffur) — nur Mitglieder mit beiden Fensterwerten.
     *
     * @param  list<array{userName:string, before: float|null, after: float|null}>  $members
     * @return list<array{x: string, y: float, y2: float}>
     */
    private function beforeAfterSeries(array $members): array {
        $series = [];
        foreach ($members as $member) {
            if ($member['before'] === null || $member['after'] === null) {
                continue;
            }
            $series[] = ['x' => $member['userName'], 'y' => $member['before'], 'y2' => $member['after']];
        }

        return $series;
    }

    /**
     * Kohortenverlauf über die Fensterwochen relativ zum Erwerbsdatum
     * (W+0 = Erwerbswoche); Wochen ohne Buchungen liefert der Builder nicht
     * mit — leere Serie ⇒ Leerzustand (§Diagramm-UX).
     *
     * @param  list<array{week:int, value: float, minutes:int}>  $weekly
     * @return list<array{x: string, y: float}>
     */
    private function weeklySeries(array $weekly): array {
        return array_map(static fn(array $point): array => [
            'x' => sprintf('W%+d', $point['week']),
            'y' => $point['value'],
        ], $weekly);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, int|string>  $auditFilters
     */
    private function exportCsv(array $result, string $qualName, string $metricLabelKey, array $auditFilters, Request $request): Response {
        /** @var list<array<string, mixed>> $members */
        $members = $result['members'];

        $rows = [];
        $rows[] = [
            (string) __('reporting.cohort.member'),
            (string) __('reporting.cohort.acquired_on'),
            (string) __('reporting.cohort.before'),
            (string) __('reporting.cohort.after'),
            (string) __('reporting.cohort.delta'),
            (string) __('reporting.cohort.improved'),
        ];
        $num = static fn($v): string => $v === null ? '' : NumberHelper::toUSFormat((float) $v, 2);
        foreach ($members as $m) {
            $rows[] = [
                (string) $m['userName'],
                (string) ($m['acquiredOn'] ?? ''),
                $num($m['before']),
                $num($m['after']),
                $num($m['delta']),
                $m['improved'] === null ? '' : ($m['improved'] ? '1' : '0'),
            ];
        }

        return $this->csvWithMetadata(
            $rows,
            sprintf('kohorte_%s.csv', preg_replace('/[^a-z0-9]+/i', '_', $qualName)),
            'cohort-comparison',
            array_merge(['qualification' => $qualName, 'metric' => (string) __($metricLabelKey)], $auditFilters),
            $request,
        );
    }
}
