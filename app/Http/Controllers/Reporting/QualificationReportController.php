<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, ResolvesReportScope, ResolvesStandardReportFilters, WritesReportCsv};
use App\Models\{Qualification, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Qualifikationsmatrix: Mitarbeiter × Qualifikationen mit Ablaufdaten.
 */
class QualificationReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;
    use WritesReportCsv;

    private const EXPIRY_WARN_DAYS = 30;

    public function index(Request $request): View|SymfonyResponse {
        $userId = (int) Auth::id();
        $isAdmin = $this->viewerIsAdmin();

        $today = Carbon::today();
        $warnDate = $today->copy()->addDays(self::EXPIRY_WARN_DAYS);

        // Stichtagsreport (heute) — der Zeitraum dient nur Filterkontext/Links.
        [$rangeFrom, $rangeTo] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['user', 'team'], $rangeFrom, $rangeTo);

        /** @var Collection<int, Qualification> $qualifications */
        $qualifications = Qualification::query()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'is_active']);

        $usersQuery = User::inCurrentOrganization()
            ->whereHas('qualifications')
            ->orderBy('name');
        if (! $isAdmin) {
            $usersQuery->where('id', $userId);
        }
        $filters->applyUserAndTeam($usersQuery, 'id');
        /** @var Collection<int, User> $users */
        $users = $usersQuery->with(['qualifications:id,name'])->get(['id', 'name']);

        /** @var array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>> $matrix */
        $matrix = [];
        /** @var array<int, array{valid: int, expiring: int, expired: int}> $stateByQualification */
        $stateByQualification = [];
        $expiring = 0;
        $expired = 0;
        $totalAssignments = 0;
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $matrix[$uid] = [];
            foreach ($u->qualifications as $q) {
                $totalAssignments++;
                $pivot = $q->getRelationValue('pivot');
                $validUntil = $pivot !== null ? $pivot->getAttribute('valid_until') : null;
                $validFrom = $pivot !== null ? $pivot->getAttribute('valid_from') : null;
                $state = 'valid';
                if ($validUntil !== null) {
                    $end = Carbon::parse((string) $validUntil)->startOfDay();
                    if ($end->lt($today)) {
                        $state = 'expired';
                        $expired++;
                    } elseif ($end->lte($warnDate)) {
                        $state = 'expiring';
                        $expiring++;
                    }
                }
                $matrix[$uid][(int) $q->id] = [
                    'valid_from' => $validFrom !== null ? Carbon::parse((string) $validFrom)->toDateString() : null,
                    'valid_until' => $validUntil !== null ? Carbon::parse((string) $validUntil)->toDateString() : null,
                    'state' => $state,
                ];
                $stateByQualification[(int) $q->id] ??= ['valid' => 0, 'expiring' => 0, 'expired' => 0];
                $stateByQualification[(int) $q->id][$state]++;
            }
        }

        $exportFilters = array_merge(['date' => $today->toDateString()], $filters->toAuditArray());

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($users, $qualifications, $matrix, $exportFilters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->exportPdf($users, $qualifications, $matrix, [
                'total_assignments' => $totalAssignments,
                'expiring' => $expiring,
                'expired' => $expired,
            ], $this->holdersSeries($qualifications, $stateByQualification), $exportFilters, $request);
        }

        return view('reports.qualifications', [
            'users' => $users,
            'qualifications' => $qualifications,
            'matrix' => $matrix,
            'isAdmin' => $isAdmin,
            'totals' => [
                'users' => $users->count(),
                'qualifications' => $qualifications->count(),
                'assignments' => $totalAssignments,
                'expiring' => $expiring,
                'expired' => $expired,
            ],
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team'],
            'holdersSeries' => $this->holdersSeries($qualifications, $stateByQualification),
            'stateSeries' => $this->stateSeries($qualifications, $stateByQualification),
            'stateBands' => $this->stateBands(),
            ...$this->standardFilterOptions(['user', 'team'], $filters),
        ]);
    }

    /**
     * Träger je Qualifikation (Top 15) — Datenkontrakt für bar-h (Screen + PDF).
     *
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array{valid: int, expiring: int, expired: int}>  $stateByQualification
     * @return list<array{x: string, y: int}>
     */
    private function holdersSeries($qualifications, array $stateByQualification): array {
        return array_values(collect($qualifications)
            ->map(fn (Qualification $q): array => [
                'x' => (string) $q->name,
                'y' => array_sum($stateByQualification[(int) $q->id] ?? []),
            ])
            ->filter(static fn (array $point): bool => $point['y'] > 0)
            ->sortByDesc('y')
            ->take(15)
            ->all());
    }

    /**
     * Zuweisungen je Qualifikation nach Gültigkeitsstatus (Top 12, gestapelt).
     *
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array{valid: int, expiring: int, expired: int}>  $stateByQualification
     * @return list<array<string, string|int>>
     */
    private function stateSeries($qualifications, array $stateByQualification): array {
        return array_values(collect($qualifications)
            ->filter(fn (Qualification $q): bool => array_sum($stateByQualification[(int) $q->id] ?? []) > 0)
            ->sortByDesc(fn (Qualification $q): int => array_sum($stateByQualification[(int) $q->id] ?? []))
            ->take(12)
            ->map(fn (Qualification $q): array => [
                'x' => (string) ($q->abbreviation ?? $q->name),
                'valid' => $stateByQualification[(int) $q->id]['valid'] ?? 0,
                'expiring' => $stateByQualification[(int) $q->id]['expiring'] ?? 0,
                'expired' => $stateByQualification[(int) $q->id]['expired'] ?? 0,
            ])
            ->all());
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function stateBands(): array {
        return [
            ['key' => 'valid', 'label' => (string) __('gültig')],
            ['key' => 'expiring', 'label' => (string) __('läuft in 30 Tagen ab')],
            ['key' => 'expired', 'label' => (string) __('abgelaufen')],
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportCsv($users, $qualifications, array $matrix, array $exportFilters, Request $request): Response {
        $filename = 'qualifikationen_' . Carbon::today()->toDateString() . '.csv';
        $rows = [];
        $header = ['Mitarbeiter'];
        foreach ($qualifications as $q) {
            $header[] = $q->name;
        }
        $rows[] = $header;
        foreach ($users as $u) {
            $line = [$u->name];
            foreach ($qualifications as $q) {
                $cell = $matrix[(int) $u->id][(int) $q->id] ?? null;
                if ($cell === null) {
                    $line[] = '';
                } else {
                    $val = $cell['valid_until'] ?? 'gültig';
                    if ($cell['state'] === 'expired') {
                        $val = 'ABGELAUFEN ' . $val;
                    } elseif ($cell['state'] === 'expiring') {
                        $val = 'LÄUFT AB ' . $val;
                    }
                    $line[] = $val;
                }
            }
            $rows[] = $line;
        }

        return $this->csvWithMetadata($rows, $filename, 'qualifications', $exportFilters, $request);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Qualification>  $qualifications
     * @param  array<int, array<int, array{valid_from: ?string, valid_until: ?string, state: string}>>  $matrix
     * @param  array{total_assignments:int, expiring:int, expired:int}  $totals
     * @param  list<array{x: string, y: int}>  $holdersSeries
     * @param  array<string, mixed>  $exportFilters
     */
    private function exportPdf($users, $qualifications, array $matrix, array $totals, array $holdersSeries, array $exportFilters, Request $request): SymfonyResponse {
        $filename = 'qualifikationen_' . Carbon::today()->toDateString() . '.pdf';
        return $this->pdfDownload('reports.pdf.qualifications', [
            'users' => $users,
            'qualifications' => $qualifications,
            'matrix' => $matrix,
            'totals' => $totals,
            'chart' => [
                'type' => 'bar-h',
                'title' => __('Träger je Qualifikation (Top 15)'),
                'unit' => __('Personen'),
                'xLabel' => __('Qualifikation'),
                'yLabel' => __('Personen'),
                'series' => $holdersSeries,
            ],
        ], $filename, 'landscape', $request, 'qualifications', $exportFilters);
    }
}
