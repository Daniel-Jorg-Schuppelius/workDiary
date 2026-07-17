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
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
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

        $rawQualId = $request->query('qualification_id');
        $qualId = Sqid::decodeOrNumeric(Qualification::class, $rawQualId);

        $result = null;
        $qualification = null;
        if ($qualId !== null) {
            $qualification = Qualification::query()->find($qualId);
            if ($qualification !== null) {
                $result = $this->builder->build($qualification, $metric, $window);

                if ($request->query('export') === 'csv') {
                    return $this->exportCsv($result, (string) $qualification->name, $metricOptions[$metric], $request);
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
            'label' => $this->globalDateRange()['label'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function exportCsv(array $result, string $qualName, string $metricLabelKey, Request $request): Response {
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
            ['qualification' => $qualName, 'metric' => (string) __($metricLabelKey)],
            $request,
        );
    }
}
