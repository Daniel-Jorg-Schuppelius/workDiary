<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\Expense\ExpenseStatus;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{ResolvesReportScope, ResolvesStandardReportFilters};
use App\Models\Expense;
use App\Support\ChartBucket;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Spesen-Report: Aggregation pro Mitarbeiter × Kategorie × Monat.
 * Bereich:
 *  - "mine": eigener User (Default)
 *  - "team": Org-weit (nur Admin)
 */
class ExpenseReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesReportScope;
    use ResolvesStandardReportFilters;

    public function index(Request $request): View {
        $authUser = Auth::user();
        [$scope, $isAdmin] = $this->resolveScopeWithAdmin($request);

        [$from, $to] = $this->resolveRange($request);

        // Der bisherige status-Parameter heißt im Standardset genauso —
        // alte Bookmarks funktionieren unverändert (Whitelist = Enum-Werte).
        $filters = $this->standardFilters(
            $request,
            ['user', 'team', 'project', 'status'],
            $from,
            $to,
            ExpenseStatus::values(),
            scope: $scope,
        );

        $query = Expense::query()
            ->with(['user:id,name', 'category:id,label,icon,color'])
            ->whereBetween('date', DateRange::days($from, $to));

        if ($scope === 'mine') {
            $query->where('user_id', Auth::id());
        } elseif ($authUser?->organization_id !== null) {
            $query->where('organization_id', $authUser->organization_id);
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }
        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }
        $filters->applyUserAndTeam($query);

        /** @var Collection<int, Expense> $expenses */
        $expenses = $query->get();

        [$rows, $months, $totalsPerUser, $totalsPerCategory, $totalsPerMonth, $grandTotal] = $this->aggregate($expenses);
        [$monthlyCategorySeries, $categoryBands] = $this->monthlyCategorySeries($expenses, $from, $to);

        return view('reports.expenses', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'scope' => $scope,
            'isAdmin' => $isAdmin,
            'statusOptions' => ExpenseStatus::options(),
            'rows' => $rows,
            'months' => $months,
            'totalsPerUser' => $totalsPerUser,
            'totalsPerCategory' => $totalsPerCategory,
            'totalsPerMonth' => $totalsPerMonth,
            'grandTotal' => $grandTotal,
            'standardFilters' => $filters,
            'filterFields' => ['user', 'team', 'project', 'status'],
            'monthlyCategorySeries' => $monthlyCategorySeries,
            'categoryBands' => $categoryBands,
            'topSpenderSeries' => $this->topSpenderSeries($totalsPerUser),
            'periodPhrase' => $this->periodPhrase($this->bucketGranularity($from, $to)),
            'periodAxis' => $this->periodAxisLabel($this->bucketGranularity($from, $to)),
            ...$this->standardFilterOptions(['user', 'team', 'project'], $filters),
        ]);
    }

    /**
     * Spesen (brutto, €) je Monat, gestapelt nach Kategorie — Top 4
     * Kategorien + „Rest"-Sammelband (§Diagramm-UX: leere Serie ohne Daten).
     *
     * @param  Collection<int, Expense>  $expenses
     * @return array{0: list<array<string, string|float>>, 1: list<array{key: string, label: string}>}
     */
    private function monthlyCategorySeries(Collection $expenses, CarbonImmutable $from, CarbonImmutable $to): array {
        if ($expenses->isEmpty()) {
            return [[], []];
        }

        $granularity = $this->bucketGranularity($from, $to);
        /** @var array<string, array<string, float>> $byBucketCategory */
        $byBucketCategory = [];
        /** @var array<string, float> $categoryTotals */
        $categoryTotals = [];
        foreach ($expenses as $expense) {
            $category = $expense->category->label ?? '—';
            $bucketKey = ChartBucket::keyLabel($granularity, CarbonImmutable::parse((string) $expense->date))[0];
            $amount = ($expense->amount_gross?->toFloat() ?? 0.0);
            $byBucketCategory[$bucketKey][$category] = ($byBucketCategory[$bucketKey][$category] ?? 0.0) + $amount;
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0.0) + $amount;
        }

        arsort($categoryTotals);
        $topCategories = array_slice(array_keys($categoryTotals), 0, 4);
        $hasRest = count($categoryTotals) > count($topCategories);

        $bands = [];
        /** @var array<string, string> $keyByCategory */
        $keyByCategory = [];
        foreach ($topCategories as $i => $category) {
            $key = 'cat_' . $i;
            $keyByCategory[$category] = $key;
            $bands[] = ['key' => $key, 'label' => $category];
        }
        if ($hasRest) {
            $bands[] = ['key' => 'rest', 'label' => (string) __('Rest')];
        }

        $series = [];
        foreach ($this->buildBucketsInRange($from, $to) as $bucket) {
            $row = ['x' => $bucket['shortLabel']];
            foreach ($bands as $band) {
                $row[$band['key']] = 0.0;
            }
            foreach ($byBucketCategory[$bucket['key']] ?? [] as $category => $amount) {
                $key = $keyByCategory[$category] ?? 'rest';
                if (! $hasRest && ! isset($keyByCategory[$category])) {
                    continue;
                }
                $row[$key] = round((float) $row[$key] + $amount, 2);
            }
            $series[] = $row;
        }

        return [$series, $bands];
    }

    /**
     * Top-Verursacher (brutto, €) — Top 15 Mitarbeiter.
     *
     * @param  array<string, float>  $totalsPerUser
     * @return list<array{x: string, y: float}>
     */
    private function topSpenderSeries(array $totalsPerUser): array {
        arsort($totalsPerUser);

        return array_values(collect($totalsPerUser)
            ->filter(static fn(float $sum): bool => $sum > 0)
            ->take(15)
            ->map(static fn(float $sum, string $name): array => ['x' => $name, 'y' => round($sum, 2)])
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, Expense>  $expenses
     * @return array{
     *     0: array<string, array{user:string, category:string, color:?string, icon:?string, months:array<string,float>, total:float}>,
     *     1: array<int, string>,
     *     2: array<string, float>,
     *     3: array<string, float>,
     *     4: array<string, float>,
     *     5: float
     * }
     */
    private function aggregate(Collection $expenses): array {
        $rows = [];
        $months = [];
        $totalsPerUser = [];
        $totalsPerCategory = [];
        $totalsPerMonth = [];
        $grandTotal = 0.0;

        foreach ($expenses as $expense) {
            $userName = $expense->user->name ?? '—';
            $categoryLabel = $expense->category->label ?? '—';
            $color = $expense->category?->color;
            $icon = $expense->category?->icon;
            $month = $expense->date->format('Y-m');
            $amount = ($expense->amount_gross?->toFloat() ?? 0.0);

            $rowKey = $userName . '||' . $categoryLabel;
            if (! isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'user' => $userName,
                    'category' => $categoryLabel,
                    'color' => $color,
                    'icon' => $icon,
                    'months' => [],
                    'total' => 0.0,
                ];
            }
            $rows[$rowKey]['months'][$month] = ($rows[$rowKey]['months'][$month] ?? 0.0) + $amount;
            $rows[$rowKey]['total'] += $amount;

            $totalsPerUser[$userName] = ($totalsPerUser[$userName] ?? 0.0) + $amount;
            $totalsPerCategory[$categoryLabel] = ($totalsPerCategory[$categoryLabel] ?? 0.0) + $amount;
            $totalsPerMonth[$month] = ($totalsPerMonth[$month] ?? 0.0) + $amount;
            $grandTotal += $amount;

            $months[$month] = true;
        }

        $monthKeys = array_keys($months);
        sort($monthKeys);

        uksort($rows, fn(string $a, string $b): int => strnatcasecmp($a, $b));

        return [$rows, $monthKeys, $totalsPerUser, $totalsPerCategory, $totalsPerMonth, $grandTotal];
    }
}
